<?php

namespace App\Models;

use App\Enums\ServiceRequestStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * تغيير في حالة طلب: من نفّذه، ومن أي حالة إلى أيها، والرد على المتقدم (يراه)، والملاحظة
 * الداخلية (للموظفين فقط). لا يُعدَّل بعد إنشائه.
 *
 * @property ServiceRequestStatus|null $from_status
 * @property ServiceRequestStatus $to_status
 */
#[Fillable(['actor_id', 'from_status', 'to_status', 'message', 'internal_note'])]
class ServiceRequestEvent extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => ServiceRequestStatus::class,
            'to_status' => ServiceRequestStatus::class,
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
