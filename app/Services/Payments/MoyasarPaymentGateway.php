<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\MoyasarPayload;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * ميسر عبر فواتيرها: الفاتورة تُنشأ من الخادم بالمبلغ (POST /v1/invoices) ويُحوَّل الدافع إلى صفحتها،
 * والنتيجة تُقرأ من الفاتورة (GET /v1/invoices/:id) أو من الإشعار بعد مطابقة رمزه السري
 * (Secret Token في لوحة ميسر). والاسترداد على الدفعة داخل الفاتورة. المفتاح السري من .env وحده.
 */
class MoyasarPaymentGateway implements PaymentGateway
{
    public function __construct(
        private readonly string $secretKey,
        private readonly string $webhookSecret,
        private readonly string $baseUrl = 'https://api.moyasar.com/v1',
    ) {}

    public function start(Payment $payment, string $returnUrl): GatewayPayment
    {
        $response = $this->client()->post('invoices', [
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'description' => $payment->purpose->getLabel().' — '.$payment->uuid,
            'success_url' => $returnUrl,
            'back_url' => $returnUrl,
            'metadata' => ['payment' => $payment->uuid],
        ]);

        return MoyasarPayload::fromInvoice((array) $response->json());
    }

    public function fetch(string $reference): GatewayPayment
    {
        return MoyasarPayload::fromInvoice((array) $this->client()->get('invoices/'.rawurlencode($reference))->json());
    }

    public function webhook(Request $request): ?GatewayEvent
    {
        return MoyasarPayload::webhook($request, $this->webhookSecret);
    }

    public function refund(Payment $payment): GatewayPayment
    {
        if ($payment->gateway_payment_id === null) {
            throw new RuntimeException('The payment has no gateway payment id to refund.');
        }

        $response = $this->client()->post('payments/'.rawurlencode($payment->gateway_payment_id).'/refund');

        return MoyasarPayload::fromPayment((array) $response->json(), (string) $payment->gateway_reference);
    }

    private function client(): PendingRequest
    {
        if ($this->secretKey === '') {
            throw new RuntimeException('MOYASAR_SECRET_KEY is not set.');
        }

        return Http::baseUrl($this->baseUrl)
            ->withBasicAuth($this->secretKey, '')
            ->acceptJson()
            ->timeout((int) config('payments.timeout_seconds', 15))
            ->throw();
    }
}
