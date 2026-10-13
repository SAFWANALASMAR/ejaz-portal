<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * تحكيم مشاركة مسندة إلى محكّم: درجة كل معيار {معرّف المعيار: درجة}، والمجموع الموزون من 100،
 * ووقت إنهائه. ولا تُدخل الدرجات ولا تُعدَّل بعد اعتماد النتائج (ScoreSubmission).
 *
 * @property array<mixed>|null $scores
 * @property Carbon|null $completed_at
 */
class SubmissionReview extends Model
{
    use LogsActivity;

    /**
     * لا تعبئة جماعية: الإجراءات تضبط الحقول بنفسها.
     *
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scores' => 'array',
            'total' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<CompetitionSubmission, $this>
     */
    public function submission(): BelongsTo
    {
        return $this->belongsTo(CompetitionSubmission::class, 'submission_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function score(CompetitionCriterion $criterion): ?int
    {
        $value = ((array) $this->scores)[(string) $criterion->id] ?? null;

        return is_int($value) ? $value : null;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['submission_id', 'reviewer_id', 'scores', 'total', 'completed_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
