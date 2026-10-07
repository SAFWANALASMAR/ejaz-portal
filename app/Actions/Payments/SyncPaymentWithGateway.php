<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;

/**
 * قراءة حالة العملية من البوابة واعتمادها: عند رجوع المتصفح لا يُعتمد ما في رابطه بل ما تقوله
 * البوابة. والعملية التي لم تبدأ عند البوابة تبقى كما هي.
 */
class SyncPaymentWithGateway
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentOutcome $record,
    ) {}

    public function handle(Payment $payment): Payment
    {
        if ($payment->gateway_reference === null) {
            return $payment;
        }

        return $this->record->handle($payment, $this->gateway->fetch($payment->gateway_reference));
    }
}
