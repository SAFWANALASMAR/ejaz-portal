<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أحداث السجل المالي الثابت لكل عملية:
 * - «مخالفة»: إشعار بمبلغ أو عملة لا يطابقان السجل، فلا يُعتمد.
 * - «أُهمل»: إشعار صحيح وصل لسجل مكتمل (مدفوع أو مسترد من قبل)، يُقيَّد ولا يغيّر شيئاً.
 * - «فشل الاسترداد» و«خطأ البوابة»: فشل الاتصال بالبوابة عند الاسترداد أو عند البدء.
 */
enum PaymentEventType: string implements HasLabel
{
    case Started = 'started';
    case Paid = 'paid';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Mismatch = 'mismatch';
    case Ignored = 'ignored';
    case RefundFailed = 'refund_failed';
    case GatewayError = 'gateway_error';

    public function getLabel(): string
    {
        return __('payments.event_types.'.$this->value);
    }
}
