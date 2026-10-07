<?php

namespace App\Support\Payments;

/**
 * إشعار من البوابة بعد التحقق من توقيعه: معرّفه (يُحفظ بقيد فريد فلا يُعالج مرتين) وحالة العملية.
 */
final readonly class GatewayEvent
{
    public function __construct(
        public string $id,
        public GatewayPayment $payment,
    ) {}
}
