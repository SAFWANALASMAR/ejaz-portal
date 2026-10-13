<?php

namespace App\Support\Payments;

use App\Enums\PaymentStatus;

/**
 * حالة عملية كما تراها البوابة: المرجع (رقم الفاتورة)، والحالة مقابَلة بحالات البوابة الأربع، والمبلغ
 * بالهللات، والعملة، ورقم الدفعة داخل الفاتورة (للاسترداد)، ورسالة البوابة، ورابط صفحة الدفع عند البدء.
 *
 * و`attempt` تعني أن الحالة لمحاولة دفع واحدة (إشعار دفعة أو استرداد) لا للفاتورة كلها: فالفاتورة تقبل
 * محاولات متعددة، وفشل محاولة لا يُنهيها (RecordPaymentOutcome). و`refunded` المبلغ المسترد بالهللات إن
 * ذكرته البوابة، فالاسترداد الجزئي لا يُعلَّم «مستردة».
 */
final readonly class GatewayPayment
{
    public function __construct(
        public string $reference,
        public PaymentStatus $status,
        public int $amount,
        public string $currency,
        public ?string $paymentId = null,
        public ?string $message = null,
        public ?string $checkoutUrl = null,
        public bool $attempt = false,
        public ?int $refunded = null,
    ) {}
}
