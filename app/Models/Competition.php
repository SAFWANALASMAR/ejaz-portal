<?php

namespace App\Models;

use App\Enums\CompetitionStatus;
use App\Enums\RankingScope;
use App\Enums\SubmissionStatus;
use App\Enums\WorkType;
use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Support\CompetitionPage;
use Database\Factories\CompetitionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
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
 * @property array<string, mixed>|null $page_content
 * @property array<mixed>|null $prize_table
 * @property RankingScope $ranking_scope
 * @property Carbon|null $ranking_locked_at
 */
#[Fillable(['slug', 'title', 'description', 'terms', 'prizes', 'categories', 'opens_at', 'closes_at', 'shuffle_questions', 'shuffle_options', 'is_published', 'exam_mode', 'intro_video_path', 'intro_video_seconds', 'work_types', 'student_mode', 'certificate_background', 'page_content', 'prize_table', 'ranking_scope', 'show_round_dates'])]
class Competition extends Model
{
    /** @use HasFactory<CompetitionFactory> */
    use FlushesContentCache, HasFactory, HasUuids, LogsActivity, Publishable, SoftDeletes;

    /**
     * الحقول التي لا تُنشر وفيها علامة النص الناقص، وقيم المصفوفات منها بما في داخلها (Publishable).
     *
     * @var list<string>
     */
    protected array $missingTextFields = ['title', 'description', 'terms', 'prizes', 'page_content', 'prize_table'];

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
            'student_mode' => 'boolean',
            'page_content' => 'array',
            'prize_table' => 'array',
            'ranking_scope' => RankingScope::class,
            'show_round_dates' => 'boolean',
            'ranking_locked_at' => 'datetime',
        ];
    }

    /**
     * مراحل مسابقة الطلاب بترتيبها (plans/student-competition.md).
     *
     * @return HasMany<CompetitionRound, $this>
     */
    public function rounds(): HasMany
    {
        return $this->hasMany(CompetitionRound::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * محاولات الطلاب في كل المراحل (لوحة النتائج).
     *
     * @return HasManyThrough<CompetitionAttempt, CompetitionRound, $this>
     */
    public function attempts(): HasManyThrough
    {
        return $this->hasManyThrough(CompetitionAttempt::class, CompetitionRound::class, 'competition_id', 'round_id');
    }

    /**
     * حقول التسجيل المخصصة.
     *
     * @return HasMany<CompetitionField, $this>
     */
    public function fields(): HasMany
    {
        return $this->hasMany(CompetitionField::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @return HasMany<CompetitionPassage, $this>
     */
    public function passages(): HasMany
    {
        return $this->hasMany(CompetitionPassage::class)->orderBy('id');
    }

    /**
     * الفئات المفتوحة في هذا الإطلاق.
     *
     * @return BelongsToMany<CompetitionCategory, $this>
     */
    public function openCategories(): BelongsToMany
    {
        return $this->belongsToMany(CompetitionCategory::class, 'competition_category_competition', 'competition_id', 'category_id')
            ->orderBy('sort_order')->orderBy('competition_categories.id');
    }

    /**
     * منشئ المسابقة: يدير نتائجها (CompetitionAccess::managesResults)، ويطّلع على أرقام هويات مسجليها كاملة إن مُنح الخيار
     * (CompetitionPolicy::viewNationalId).
     *
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
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
     * بنك المسابقة في نموذجها (الأعمال والاختبار الإلكتروني)، بلا أسئلة مراحل مسابقة الطلاب، فلا يصل بنك المراحل
     * إلى نموذج المشاركة ولا التصحيح القديم ولا حفظ النموذج.
     *
     * @return HasMany<CompetitionQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CompetitionQuestion::class)->whereNull('round_id')->orderBy('sort_order')->orderBy('id');
    }

    /**
     * بنك مراحل مسابقة الطلاب كلها (QuestionBankRelationManager).
     *
     * @return HasMany<CompetitionQuestion, $this>
     */
    public function roundQuestions(): HasMany
    {
        return $this->hasMany(CompetitionQuestion::class)->whereNotNull('round_id')->orderBy('sort_order')->orderBy('id');
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
        // وسحب المتساوين في مرحلة من مسابقة الطلاب لا يقفل مواعيد المسابقة (RunTieDraw)
        return $this->exists && ($this->resultsApproved() || $this->draws()->whereNull('round_id')->whereNull('gender')->whereDoesntHave('void')->exists());
    }

    /**
     * نطاق الترتيب وجدول الجوائز يُقفلان متى سُحب في الترتيب التراكمي (ولو أُلغي السحب) أو نُشرت نتائج كل الفروع،
     * فلا يتغير على أساسه الترتيب ولا حد المراكز بعد إعلانه (مراجعة المشرف على المطوية). ولحظة القفل تُسجَّل
     * (ranking_locked_at)، فلا تفكه إضافة مرحلة بعد نشر كل الفروع.
     */
    public function cumulativeLocked(): bool
    {
        if (! $this->exists || ! $this->student_mode) {
            return false;
        }

        return $this->ranking_locked_at !== null
            || $this->draws()->whereNull('round_id')->whereNotNull('gender')->exists()
            || ($this->rounds()->exists() && $this->rounds()->whereNull('results_published_at')->doesntExist());
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
        if ($this->exam_mode || $this->student_mode) {
            return [];
        }

        return array_values(array_filter(array_map(fn ($type): ?WorkType => is_string($type) ? WorkType::tryFrom($type) : null, (array) $this->work_types)));
    }

    /**
     * فقرة من نصوص صفحة مسابقة الطلاب (CompetitionPage)، أو null إن لم تُكتب.
     */
    public function pageText(string $key): ?string
    {
        $value = ((array) $this->page_content)[$key] ?? null;

        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }

    /**
     * قائمة بنود من نصوص صفحة مسابقة الطلاب (CompetitionPage).
     *
     * @return list<string>
     */
    public function pageList(string $key): array
    {
        return CompetitionPage::items(((array) $this->page_content)[$key] ?? []);
    }

    public function hasGuide(): bool
    {
        return collect(CompetitionPage::GUIDE)->contains(fn (string $key): bool => $this->pageList($key) !== []);
    }

    /**
     * دليل الجمعية كاملاً (CompetitionPage::GUIDE_BOOK): الكتاب المختار ما دام منشوراً «للقراءة فقط» بملفه، وإلا null
     * فيُخفى زره. يُفحص عند كل عرض، فإلغاء نشر الكتاب أو جعله قابلاً للتحميل يخفي الزر فوراً.
     */
    public function guideBook(): ?LibraryItem
    {
        $id = ((array) $this->page_content)[CompetitionPage::GUIDE_BOOK] ?? null;

        return is_numeric($id) ? LibraryItem::query()->readOnlyBooks()->find((int) $id) : null;
    }

    /**
     * هل الكتاب دليل مسابقة طلاب منشورة؟ لحد قارئ الدليل الأوسع أثناء النوافذ (LibraryController::serveToReader)، مخزّن دقيقة
     * فلا يكون استعلاماً لكل فتحة. والمقارنة نصاً: ->> يرد النص في PostgreSQL.
     */
    public static function usesAsGuideBook(LibraryItem $item): bool
    {
        return (bool) Cache::remember('competition-guide-book:'.$item->id, 60, fn (): bool => static::query()
            ->where('student_mode', true)
            ->where('is_published', true)
            ->where('page_content->'.CompetitionPage::GUIDE_BOOK, (string) $item->id)
            ->exists());
    }

    /**
     * جوائز المراكز لكل جنس بترتيبها: المركز من موضعه في الجدول.
     *
     * @return list<array{place: int, male: int, female: int}>
     */
    public function prizeRows(): array
    {
        $prizes = [];

        foreach (array_values((array) $this->prize_table) as $index => $row) {
            $prizes[] = [
                'place' => $index + 1,
                'male' => (int) (is_array($row) ? ($row['male'] ?? 0) : 0),
                'female' => (int) (is_array($row) ? ($row['female'] ?? 0) : 0),
            ];
        }

        return $prizes;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
