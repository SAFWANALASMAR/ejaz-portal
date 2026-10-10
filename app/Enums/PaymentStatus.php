<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة عملية الدفع. تنتقل عبر RecordPaymentOutcome وحده: معلّقة ثم مدفوعة أو فاشلة، والمدفوعة
 * قد تُسترد. والحالات الكثيرة عند البوابة تُقابَل بهذه الأربع (MoyasarPayload::status).
 */
enum PaymentStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function getLabel(): string
    {
        return __('payments.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Paid => 'success',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }

    public function isFinal(): bool
    {
        return $this !== self::Pending;
    }
}
