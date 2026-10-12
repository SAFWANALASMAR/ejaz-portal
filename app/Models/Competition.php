<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\SubmissionStatus;
use App\Enums\WorkType;
use App\Models\Concerns\Publishable;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مسابقة: بياناتها، ومعايير تقييمها، وبنك أسئلتها. التقديم يُفتح ويُغلق آلياً من موعديها
 * (status)، وتُحفظ عبر SaveCompetition.
 *
 * @property Carbon $opens_at
 * @property Carbon $closes_at
 * @property Carbon|null $results_approved_at
 * @property array<mixed>|null $categories
 * @property array<mixed>|null $work_types
 */
#[Fillable(['slug', 'title', 'description', 'terms', 'prizes', 'categories', 'opens_at', 'closes_at', 'shuffle_questions', 'shuffle_options', 'is_published', 'exam_mode', 'intro_video_path', 'intro_video_seconds', 'work_types'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use HasFactory, HasUuids, LogsActivity, Publishable, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categories' => 'array',
            'opens_at' => 'datetime',
            'closes_at' => 'datetime',
            'shuffle_questions' => 'boolean',
            'shuffle_options' => 'boolean',
            'is_published' => 'boolean',
            'results_approved_at' => 'datetime',
            'exam_mode' => 'boolean',
            'intro_video_seconds' => 'integer',
            'work_types' => 'array',
        ];
    }

    /**
     * المعرّف العشوائي في عمود uuid، والمعرّف الرقمي يبقى المفتاح.
     *
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return HasMany<CompetitionCriterion, $this>
     */
    public function criteria(): HasMany
    {
        return $this->hasMany(CompetitionCriterion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CompetitionQuestion::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(CompetitionSubmission::class);
    }

    /**
     * المحكّمون: حسابات بدور reviewer يُسند إليهم من مشاركات المسابقة.
     *
     * @return BelongsToMany<User, $this>
     */
    public function reviewers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_reviewer')->withTimestamps();
    }

    /**
     * @return HasManyThrough<SubmissionReview, CompetitionSubmission, $this>
     */
    public function reviews(): HasManyThrough
    {
        return $this->hasManyThrough(SubmissionReview::class, CompetitionSubmission::class, 'competition_id', 'submission_id');
    }

    /**
     * @return HasMany<CompetitionDraw, $this>
     */
    public function draws(): HasMany
    {
        return $this->hasMany(CompetitionDraw::class)->orderByDesc('executed_at');
    }

    public function resultsApproved(): bool
    {
        return $this->results_approved_at !== null;
    }

    /**
     * المعايير تُقفل بعد أول إسناد، لأن درجات المحكّمين مربوطة بها.
     */
    public function criteriaLocked(): bool
    {
        return $this->exists && $this->reviews()->exists();
    }

    /**
     * بنك الأسئلة يُقفل بعد أول إجابة محفوظة أو مشاركة مرسلة (ولو محذوفة)، لأن الإجابات مربوطة
     * بأسئلته. والمسودة الفارغة (فتح النموذج وحده) لا تقفله.
     */
    public function questionsLocked(): bool
    {
        return $this->exists && $this->submissions()->withTrashed()
            ->where(fn (Builder $query) => $query
                ->where('status', SubmissionStatus::Submitted)
                // الإجابات الفارغة تُحفظ [] وغير الفارغة كائن بمعرّفات الأسئلة
                ->orWhere(fn (Builder $query) => $query->whereNotNull('answers')->where('answers', '!=', '[]'))
                ->orWhereHas('videos'))
            ->exists();
    }

    public function status(?Carbon $now = null): CompetitionStatus
    {
        $now ??= now();

        return match (true) {
            ! $this->is_published => CompetitionStatus::Draft,
            $now->lt($this->opens_at) => CompetitionStatus::Upcoming,
            $now->lt($this->closes_at) => CompetitionStatus::Open,
            default => CompetitionStatus::Closed,
        };
    }

    public function isOpen(): bool
    {
        return ! $this->trashed() && $this->status() === CompetitionStatus::Open;
    }

    /**
     * بعد اعتماد النتائج أو السحب تُقفل المواعيد والنشر، فلا يُعاد فتح التقديم وتتغير قائمة المشمولين.
     */
    public function scheduleLocked(): bool
    {
        // السحب الملغى لا يقفل، فيُصحَّح موعد خاطئ كان سبب الإلغاء
        return $this->exists && ($this->resultsApproved() || $this->draws()->whereDoesntHave('void')->exists());
    }

    /**
     * @return list<string>
     */
    public function categoryList(): array
    {
        return array_values(array_filter((array) $this->categories, fn ($category): bool => is_string($category) && $category !== ''));
    }

    /**
     * أنواع الأعمال المسموح رفعها في المسابقة. والاختبار الإلكتروني بلا أعمال.
     *
     * @return list<WorkType>
     */
    public function workTypeList(): array
    {
        if ($this->exam_mode) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($type): ?WorkType => is_string($type) ? WorkType::tryFrom($type) : null, (array) $this->work_types)));
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
