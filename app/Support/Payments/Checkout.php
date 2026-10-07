<?php

namespace App\Support\Payments;

use App\Models\Payment;

/**
 * نتيجة بدء عملية: السجل المعلّق ورابط صفحة الدفع عند البوابة.
 */
final readonly class Checkout
{
    public function __construct(
        public Payment $payment,
        public string $url,
    ) {}
}
