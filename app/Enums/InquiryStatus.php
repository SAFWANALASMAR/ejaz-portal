<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالات الاستفسار: من حالات الطلبات المشتركة (requirements/02) ما يلزم رسالة بلا قرار قبول أو
 * رفض: مُستلم ← قيد المراجعة ← مغلق.
 */
enum InquiryStatus: string implements HasColor, HasLabel
{
    case Received = 'received';
    case UnderReview = 'under_review';
    case Closed = 'closed';

    public function getLabel(): string
    {
        return __('inquiries.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Received => 'info',
            self::UnderReview => 'warning',
            self::Closed => 'gray',
        };
    }
}
