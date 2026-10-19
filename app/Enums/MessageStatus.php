<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة رسالة في سجل قنوات التواصل. delivered وread من إشعارات التسليم (واتساب).
 */
enum MessageStatus: string implements HasColor, HasLabel
{
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    // تجاوز حد الرقم في اليوم، فلم تُرسل
    case Throttled = 'throttled';
    // الاستعمال بلا قالب مضبوط (واتساب)، فلم تُرسل
    case Skipped = 'skipped';

    public function getLabel(): string
    {
        return __('messaging.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Sent => 'info',
            self::Delivered, self::Read => 'success',
            self::Failed => 'danger',
            self::Throttled, self::Skipped => 'warning',
        };
    }

    /**
     * ترتيب التقدم، فلا يعيد إشعار متأخر الحالة إلى الوراء.
     */
    public function rank(): int
    {
        return match ($this) {
            self::Sent => 1,
            self::Delivered => 2,
            self::Read => 3,
            default => 0,
        };
    }
}
