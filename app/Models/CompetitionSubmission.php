<?php

namespace App\Models;

use App\Enums\EducationStage;
use App\Enums\ExamStage;
use App\Enums\QuestionType;
use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use Database\Factories\CompetitionSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * مشاركة متقدم في مسابقة: واحدة لكل متقدم في كل مسابقة (قيد فريد). مسودة حتى الإرسال، ثم مقفلة
 * برقم مرجعي. والإجابات {معرّف السؤال: النص أو مفتاح الخيار}، ومقاطع الفيديو في submission_videos.
 *
 * @property SubmissionStatus $status
 * @property SaudiRegion|null $region
 * @property array<mixed>|null $answers
 * @property Carbon|null $submitted_at
 * @property EducationStage|null $education_stage
 * @property Carbon|null $registered_at
 * @property Carbon|null $intro_started_at
 * @property Carbon|null $intro_watched_at
 * @property Carbon|null $instructions_accepted_at
 */
#[Fillable(['category', 'region', 'answers'])]
#[Hidden(['national_id', 'national_id_hash'])]
class CompetitionSubmission extends Model
{
    /** @use HasFactory<CompetitionSubmissionFactory> */
    use HasFactory, HasUuids, LogsActivity, SoftDeletes;

    /**
     * القيم الافتراضية مطابقة لقاعدة البيانات، حتى تتوفر في النموذج قبل إعادة قراءته.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => SubmissionStatus::class,
            'region' => SaudiRegion::class,
            'answers' => 'array',
            'submitted_at' => 'datetime',
            'auto_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'education_stage' => EducationStage::class,
            'national_id' => 'encrypted',
            'registered_at' => 'datetime',
            'intro_started_at' => 'datetime',
            'intro_watched_at' => 'datetime',
            'instructions_accepted_at' => 'datetime',
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
     * المسابقة ولو حُذفت حذفاً ناعماً، فتبقى المشاركة مقروءة لصاحبها (مشاركاتي وتصدير بياناته).
     *
     * @return BelongsTo<Competition, $this>
     */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<SubmissionVideo, $this>
     */
    public function videos(): HasMany
    {
        return $this->hasMany(SubmissionVideo::class, 'submission_id');
    }

    /**
     * @return HasMany<SubmissionReview, $this>
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(SubmissionReview::class, 'submission_id');
    }

    /**
     * @return HasMany<SubmissionWork, $this>
     */
    public function works(): HasMany
    {
        return $this->hasMany(SubmissionWork::class, 'submission_id')->orderBy('id');
    }

    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
    }

    /**
     * مرحلة المتقدم في رحلة الاختبار: أول مرحلة لم يكملها. والخادم يرفض كل طلب لمرحلة بعدها.
     */
    public function examStage(): ExamStage
    {
        return match (true) {
            ! $this->isDraft() => ExamStage::Submitted,
            $this->registered_at === null => ExamStage::Registration,
            $this->intro_watched_at === null => ExamStage::Intro,
            $this->instructions_accepted_at === null => ExamStage::Instructions,
            default => ExamStage::Questions,
        };
    }

    public function answer(CompetitionQuestion $question): ?string
    {
        $value = ((array) $this->answers)[(string) $question->id] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * أسئلة المسابقة بترتيب هذا المتقدم: ثابت له في كل زيارة، ومختلف بين المتقدمين إن فُعّل الخلط.
     *
     * @return Collection<int, CompetitionQuestion>
     */
    public function orderedQuestions(): Collection
    {
        $questions = $this->competition->questions;

        return $this->competition->shuffle_questions
            ? $questions->sortBy(fn (CompetitionQuestion $question): string => hash('sha256', $this->uuid.'|q|'.$question->id))->values()
            : $questions;
    }

    /**
     * خيارات السؤال بترتيب هذا المتقدم، بلا الإجابة الصحيحة.
     *
     * @return list<array{key: string, label: string}>
     */
    public function orderedOptions(CompetitionQuestion $question): array
    {
        $options = $question->optionList();

        if ($this->competition->shuffle_options && $question->type === QuestionType::MultipleChoice) {
            usort($options, fn (array $a, array $b): int => strcmp(
                hash('sha256', $this->uuid.'|o|'.$question->id.'|'.$a['key']),
                hash('sha256', $this->uuid.'|o|'.$question->id.'|'.$b['key']),
            ));
        }

        return $options;
    }

    /**
     * قيمة الخيار في صفحة السؤال: رمز معتم لهذا المتقدم والسؤال، لا المفتاح، فلا يكشف المفتاح موضع الخيار في إدخال
     * المسؤول بعد الخلط (وقد يضع الصحيح أولاً عادةً). ويُرد إلى مفتاحه عند الحفظ (optionKey). كما في محاولة مسابقة
     * الطلاب (الاختبار الأمني 0.14.0).
     */
    public function optionToken(int $questionId, string $key): string
    {
        return substr(hash_hmac('sha256', $questionId.'|'.$key, $this->uuid.'|'.config('app.key')), 0, 12);
    }

    /**
     * مفتاح الخيار من رمزه بين خيارات السؤال، أو null.
     */
    public function optionKey(CompetitionQuestion $question, string $token): ?string
    {
        foreach ($question->optionKeys() as $key) {
            if (hash_equals($this->optionToken($question->id, $key), $token)) {
                return $key;
            }
        }

        return null;
    }

    /**
     * الحالة ووقت الإرسال والرقم المرجعي وحدها، لا الإجابات ولا بيانات المتقدم.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['status', 'submitted_at', 'reference'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
