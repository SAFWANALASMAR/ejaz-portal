<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * إعادة اختبار مرحلة لطالب بعينه (GrantCompetitionRetake): المحاولة السابقة محفوظة كما كانت للمراجعة، والسبب ومن
 * منحها، ومهلة البدء بعد إغلاق النافذة إن حُددت، ولحظة استعمالها عند بدء المحاولة الجديدة.
 *
 * @property string $reason
 * @property Carbon|null $retake_until
 * @property Carbon|null $used_at
 * @property array<string, mixed> $archived_attempt
 */
class CompetitionRetake extends Model
{
    use LogsActivity;

    /**
     * لا تعبئة جماعية: الإجراء يضبط الحقول بنفسه.
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
            'retake_until' => 'datetime',
            'used_at' => 'datetime',
            'archived_attempt' => 'array',
        ];
    }

    /**
     * @return BelongsTo<CompetitionRound, $this>
     */
    public function round(): BelongsTo
    {
        return $this->belongsTo(CompetitionRound::class, 'round_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function grantor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /**
     * ما لم يُستعمل بعد، وما زال وقته قائماً: مهلته في المستقبل، أو بلا مهلة والمرحلة جارية.
     *
     * @param  Builder<CompetitionRetake>  $query
     */
    public function scopeActive(Builder $query, CompetitionRound $round): void
    {
        $query->where('round_id', $round->id)->whereNull('used_at')
            ->where(fn (Builder $query) => $query->where('retake_until', '>', now())
                ->when($round->isRunning(), fn (Builder $query) => $query->orWhereNull('retake_until')));
    }

    /**
     * النطاق active نفسه لسجل محمّل مسبقاً، فلا استعلام لكل مرحلة (BuildStudentCompetitionCards).
     */
    public function isActiveFor(CompetitionRound $round): bool
    {
        return $this->round_id === $round->id && $this->used_at === null
            && ($this->retake_until !== null ? $this->retake_until->isFuture() : $round->isRunning());
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('competitions')->logOnly(['round_id', 'submission_id', 'user_id', 'granted_by', 'reason', 'retake_until', 'used_at'])->logOnlyDirty()->dontLogEmptyChanges();
    }
}
