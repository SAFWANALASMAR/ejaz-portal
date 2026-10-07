<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أحداث السجل المالي الثابت لكل عملية. و«مخالفة» إشعار بمبلغ أو عملة لا يطابقان السجل، فلا يُعتمد.
 */
enum PaymentEventType: string implements HasLabel
{
    case Started = 'started';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Mismatch = 'mismatch';

    public function getLabel(): string
    {
        return __('payments.event_types.'.$this->value);
    }
}
