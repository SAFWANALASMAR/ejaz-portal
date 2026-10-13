<?php

namespace App\Models;

use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * عملية دفع: مبلغها بالهللات من الخادم، وحالتها تنتقل عبر RecordPaymentOutcome وحده، وأحداثها في
 * سجل ثابت (PaymentEvent). المبلغ والعملة والغرض وصاحبها لا تُعدَّل، ولا تُحذف العملية: النموذج
 * يرفض هنا، وقاعدة البيانات ترفض بمشغّل (الترحيل).
 *
 * @property PaymentPurpose $purpose
 * @property PaymentStatus $status
 * @property Carbon|null $paid_at
 * @property Carbon|null $receipt_sent_at
 * @property Carbon|null $refunded_at
 */
#[Fillable(['user_id', 'purpose', 'amount', 'currency', 'gateway', 'donor_name', 'donor_email', 'description'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory, HasUuids;

    public const IMMUTABLE = ['amount', 'currency', 'purpose', 'user_id', 'payable_type', 'payable_id'];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
        'currency' => 'SAR',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purpose' => PaymentPurpose::class,
            'status' => PaymentStatus::class,
            'amount' => 'integer',
            'paid_at' => 'datetime',
            'receipt_sent_at' => 'datetime',
            'refunded_at' => 'datetime',
        ];
    }

    /**
     * @return list<string>
     */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    protected static function booted(): void
    {
        static::updating(function (Payment $payment): void {
            if ($payment->isDirty(self::IMMUTABLE)) {
                throw new LogicException('The amount, currency, purpose and owner of a payment cannot be changed.');
            }
        });

        static::deleting(fn () => throw new LogicException('A payment record cannot be deleted.'));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function payable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<PaymentEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(PaymentEvent::class)->orderBy('id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function refundedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'refunded_by');
    }

    /**
     * المبلغ بالريال بفواصل الآلاف، مثل «1,250.50».
     */
    public function formattedAmount(): string
    {
        return number_format($this->amount / 100, 2);
    }
}
