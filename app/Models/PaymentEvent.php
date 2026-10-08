<?php

namespace App\Models;

use App\Enums\PaymentEventType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * حدث في السجل المالي الثابت لعملية دفع: يُنشأ ولا يُعدَّل ولا يُحذف (النموذج وقاعدة البيانات).
 * والحمولة الحالة والمبلغ والعملة والرسالة فقط، بلا بيانات بطاقة.
 *
 * @property PaymentEventType $type
 * @property array<string, mixed>|null $payload
 * @property Carbon $created_at
 */
#[Fillable(['type', 'gateway_event_id', 'payload'])]
class PaymentEvent extends Model
{
    public $timestamps = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentEventType::class,
            'payload' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PaymentEvent $event): void {
            $event->created_at ??= now();
        });

        static::updating(fn () => throw new LogicException('A payment event cannot be changed.'));
        static::deleting(fn () => throw new LogicException('A payment event cannot be deleted.'));
    }

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
