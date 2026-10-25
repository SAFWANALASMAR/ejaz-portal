<?php

namespace App\Models;

use App\Enums\AttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * محاولة طالب في مرحلة: ترتيب أسئلته وخياراته العشوائي (layout)، وإجاباته {معرّف السؤال: مفتاح الخيار أو
 * النص}، وموعده النهائي في الخادم. وواحدة لكل تسجيل في كل مرحلة (قيد فريد). والدرجة لا تُعرض للطالب حتى
 * تُنشر نتائج المرحلة.
 *
 * @property AttemptStatus $status
 * @property Carbon $started_at
 * @property Carbon $deadline_at
 * @property Carbon|null $submitted_at
 * @property Carbon|null $qualified_at
 * @property list<array{passage: int|null, questions: list<array{id: int, options: list<string>}>}> $layout
 * @property array<mixed>|null $answers
 */
class CompetitionAttempt extends Model
{
    use HasUuids, LogsActivity;

    /**
     * لا تعبئة جماعية: الإجراءات تضبط الحقول بنفسها.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'in_progress',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AttemptStatus::class,
            'started_at' => 'datetime',
            'deadline_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'layout' => 'array',
            'answers' => 'array',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'essay_score' => 'decimal:2',
            'qualified_at' => 'datetime',
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
     * @return BelongsTo<CompetitionRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<CompetitionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompetitionSubmission::class, 'submission_id')->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === AttemptStatus::InProgress;
    }

    /**
     * انتهى الوقت: الموعد النهائي مع هامش الشبكة للإجابة الأخيرة.
     */
    public function expired(?Carbon $now = null): bool
    {
        return ($now ?? now())->gt($this->deadline_at->copy()->addSeconds((int) config('competitions.student.grace_seconds')));
    }

    public function secondsLeft(): int
    {
        return max(0, (int) now()->diffInSeconds($this->deadline_at, false));
    }

    /**
     * معرّفات الأسئلة بترتيب الطالب.
     *
     * @return list<int>
     */
    public function questionIds(): array
    {
        $ids = [];

        foreach ($this->layout as $unit) {
            foreach ($unit['questions'] as $question) {
                $ids[] = (int) $question['id'];
            }
        }

        return $ids;
    }

    /**
     * قيمة الخيار في الصفحة: رمز معتم لهذه المحاولة بدل مفتاحه (a، b، …)، فلا يكشف المفتاح موضع الخيار في
     * إدخال المسؤول (وقد يضع الصحيح أولاً عادةً). ويُرد إلى مفتاحه عند الحفظ (optionKey).
     */
    public function optionToken(int $questionId, string $key): string
    {
        return substr(hash_hmac('sha256', $questionId.'|'.$key, $this->uuid.'|'.config('app.key')), 0, 12);
    }

    /**
     * مفتاح الخيار من رمزه بين خيارات السؤال في ترتيب الطالب، أو null.
     *
     * @param  list<string>  $keys
     */
    public function optionKey(int $questionId, array $keys, string $token): ?string
    {
        foreach ($keys as $key) {
            if (hash_equals($this->optionToken($questionId, $key), $token)) {
                return $key;
            }
        }

        return null;
    }

    public function answer(int $questionId): ?string
    {
        $value = ((array) $this->answers)[(string) $questionId] ?? null;

        return is_string($value) && $value !== '' ? $value : null;
    }

    /**
     * الحالة والأوقات والتأهل وحدها، لا الإجابات ولا الدرجة.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['status', 'submitted_at', 'auto_submitted', 'qualified_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
