<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Contracts\HasPublicImages;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مرحلة في مسابقة الطلاب: نافذتها الزمنية، وعدد أسئلتها ومدتها، وبنك أسئلتها لكل فئة. والنتائج لا تظهر
 * للطلاب حتى تُنشر من اللوحة (results_published_at). وتُحفظ عبر SaveCompetitionRound.
 *
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $results_published_at
 * @property string|null $branch
 * @property string|null $image_path
 */
#[Fillable(['name', 'branch', 'image_path', 'sort_order', 'starts_at', 'ends_at', 'question_count', 'duration_minutes', 'qualified_only'])]
class CompetitionRound extends Model implements HasPublicImages
{
    // صورة الفرع على القرص العام، فحفظ المرحلة يمسح ذاكرة المحتوى العام كبقية نماذج الصور (ContentAdminTest يحرسه)
    use FlushesContentCache, HasUuids, LogsActivity;

    /**
     * صورة المرحلة (الفرع) الاختيارية على القرص العام.
     */
    public const IMAGE_DIRECTORY = 'competition-rounds';

    public function imageDirectory(): string
    {
        return self::IMAGE_DIRECTORY;
    }

    /**
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        return ['image_path' => is_string($this->image_path) && $this->image_path !== '' ? [$this->image_path] : []];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'question_count' => 'integer',
            'duration_minutes' => 'integer',
            'qualified_only' => 'boolean',
            'results_published_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    /**
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /**
     * @return HasMany<CompetitionQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CompetitionQuestion::class, 'round_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionPassage, $this>
     */
    public function passages(): HasMany
    {
        return $this->hasMany(CompetitionPassage::class, 'round_id')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionAttempt, $this>
     */
    public function attempts(): HasMany
    {
        return $this->hasMany(CompetitionAttempt::class, 'round_id');
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }

    /**
     * إعادة اختبار قائمة لهذا التسجيل في المرحلة (GrantCompetitionRetake)، أو null.
     */
    public function activeRetake(?CompetitionSubmission $submission): ?CompetitionRetake
    {
        if ($submission === null) {
            return null;
        }

        return CompetitionRetake::query()->active($this)->where('submission_id', $submission->id)->latest('id')->first();
    }

    /**
     * يبدأ هذا التسجيل محاولة الآن: المرحلة جارية، أو له إعادة قائمة بعد إغلاقها
     * (docs/decisions/2026-11-13-feature-competition-retake.md).
     */
    public function openFor(?CompetitionSubmission $submission): bool
    {
        return $this->isRunning() || $this->activeRetake($submission) !== null;
    }

    public function isRunning(?Carbon $now = null): bool
    {
        $now ??= now();

        return $now->gte($this->starts_at) && $now->lt($this->ends_at);
    }

    public function hasEnded(): bool
    {
        return now()->gte($this->ends_at);
    }

    public function resultsPublished(): bool
    {
        return $this->results_published_at !== null;
    }

    /**
     * البنك والمرحلة يُقفلان بعد أول محاولة، لأن ترتيب الطلاب وإجاباتهم مربوطة بأسئلتها.
     */
    public function locked(): bool
    {
        return $this->exists && $this->attempts()->exists();
    }

    /**
     * المرحلة السابقة في المسابقة نفسها، لشرط التأهل.
     */
    public function previous(): ?self
    {
        return self::query()
            ->where('competition_id', $this->competition_id)
            ->where(fn (Builder $query) => $query->where('sort_order', '<', $this->sort_order)
                ->orWhere(fn (Builder $query) => $query->where('sort_order', $this->sort_order)->where('id', '<', $this->id)))
            ->orderByDesc('sort_order')
            ->orderByDesc('id')
            ->first();
    }

    public function submittedCount(): int
    {
        return $this->attempts()->where('status', AttemptStatus::Submitted)->count();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['competition_id', 'name', 'sort_order', 'starts_at', 'ends_at', 'question_count', 'duration_minutes', 'qualified_only', 'results_published_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
