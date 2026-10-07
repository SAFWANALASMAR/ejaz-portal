<?php

namespace App\Models;

use App\Enums\QuestionType;
use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use Database\Factories\CompetitionSubmissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
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
 */
#[Fillable(['category', 'region', 'answers'])]
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

    public function isDraft(): bool
    {
        return $this->status === SubmissionStatus::Draft;
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
     * الحالة ووقت الإرسال والرقم المرجعي وحدها، لا الإجابات ولا بيانات المتقدم.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['status', 'submitted_at', 'reference'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
