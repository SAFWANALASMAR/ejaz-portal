<?php

namespace App\Models;

use App\Enums\DrawVoidRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * طلب إلغاء سحب بعد أن أُلغي في المسابقة سحب من قبل: ينتظر مشرفاً عاماً غير طالبه يعتمده (ApproveDrawVoid)
 * أو يرفضه بسبب (RejectDrawVoid)، ويسقط بعد LAPSE_DAYS بلا قرار (LapseDrawVoidRequests).
 * لا يُحذف، ولا يتغير إلا مرة من «معلّق» إلى قراره، في النموذج وفي قاعدة البيانات.
 *
 * @property DrawVoidRequestStatus $status
 * @property Carbon $requested_at
 * @property Carbon|null $decided_at
 */
class CompetitionDrawVoidRequest extends Model
{
    public const LAPSE_DAYS = 7;

    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = ['status' => 'pending'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => DrawVoidRequestStatus::class,
            'requested_at' => 'datetime',
            'decided_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (self $request): void {
            $decisionOnly = array_diff(array_keys($request->getDirty()), ['status', 'decided_by', 'decision_reason', 'decided_at']) === [];

            if ($request->getRawOriginal('status') !== DrawVoidRequestStatus::Pending->value || ! $decisionOnly) {
                throw new LogicException('A draw void request is decided once and cannot be changed.');
            }
        });
        static::deleting(fn () => throw new LogicException('A draw void request cannot be deleted.'));
    }

    /**
     * مضت مدته بلا قرار، فلا يُعتمد ولا يُرفض وإن لم يمر عليه الأمر اليومي بعد.
     */
    public function hasLapsed(): bool
    {
        return $this->requested_at->lte(now()->subDays(self::LAPSE_DAYS));
    }

    /**
     * @return BelongsTo<CompetitionDraw, $this>
     */
    public function draw(): BelongsTo
    {
        return $this->belongsTo(CompetitionDraw::class, 'draw_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
