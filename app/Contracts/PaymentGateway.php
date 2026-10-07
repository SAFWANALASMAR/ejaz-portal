<?php

namespace App\Contracts;

use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayPayment;
use Illuminate\Http\Request;

/**
 * بوابة الدفع (ميسر في الإنتاج، ووهمية في التطوير والاختبارات). المبلغ يأتي من سجل العملية دائماً،
 * والنتيجة تُقرأ من البوابة نفسها (fetch) أو من إشعارها بعد التحقق من توقيعه (webhook)،
 * ولا يُعتمد ما يحمله المتصفح.
 */
interface PaymentGateway
{
    /**
     * يبدأ العملية عند البوابة ويعيد مرجعها ورابط صفحة الدفع (checkoutUrl).
     */
    public function start(Payment $payment, string $returnUrl): GatewayPayment;

    /**
     * الحالة الحالية للعملية بمرجعها عند البوابة.
     */
    public function fetch(string $reference): GatewayPayment;

    /**
     * يفحص إشعار البوابة ويعيد حدثه، أو null إن لم يصح توقيعه أو شكله.
     */
    public function webhook(Request $request): ?GatewayEvent;

    /**
     * يسترد المبلغ كاملاً.
     */
    public function refund(Payment $payment): GatewayPayment;
}
