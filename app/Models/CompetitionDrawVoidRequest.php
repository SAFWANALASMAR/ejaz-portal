<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * طلب إلغاء سحب بعد أن أُلغي في المسابقة سحب من قبل: ينتظر اعتماد مشرف عام غير طالبه (ApproveDrawVoid).
 * سجل ثابت كسجل الإلغاء، وقاعدة البيانات ترفض تعديله وحذفه.
 *
 * @property Carbon $requested_at
 */
class CompetitionDrawVoidRequest extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['requested_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('A draw void request cannot be changed.'));
        static::deleting(fn () => throw new LogicException('A draw void request cannot be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
