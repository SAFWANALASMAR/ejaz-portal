<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use App\Support\Payments\GatewayEvent;
use App\Support\Payments\GatewayPayment;
use App\Support\Payments\MoyasarPayload;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * بوابة وهمية للتطوير والاختبارات: تحفظ حالة كل عملية في الذاكرة المؤقتة يوماً، وصفحة دفعها
 * التجريبية في البوابة نفسها (payments.fake-checkout) بزرَي «ادفع» و«أفشل». وتقبل الإشعار بصيغة
 * ميسر ورمزه السري نفسه. ولا تعمل في الإنتاج.
 */
class FakePaymentGateway implements PaymentGateway
{
    private const PREFIX = 'fake-payments:';

    public function __construct()
    {
        if (app()->isProduction()) {
            throw new RuntimeException('FakePaymentGateway must not be used in production.');
        }
    }

    public function start(Payment $payment, string $returnUrl): GatewayPayment
    {
        $reference = 'fake_'.Str::random(20);

        $this->put($reference, [
            'status' => 'initiated',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'payment_id' => null,
            'message' => null,
            'return_url' => $returnUrl,
            'checkout_url' => route('payments.fake-checkout', $payment),
        ]);

        return $this->fetch($reference);
    }

    public function fetch(string $reference): GatewayPayment
    {
        $row = $this->row($reference);

        return new GatewayPayment(
            reference: $reference,
            status: MoyasarPayload::status($row['status']),
            amount: (int) $row['amount'],
            currency: (string) $row['currency'],
            paymentId: $row['payment_id'],
            message: $row['message'],
            checkoutUrl: $row['checkout_url'],
        );
    }

    public function webhook(Request $request): ?GatewayEvent
    {
        return MoyasarPayload::webhook($request, (string) config('payments.webhook_secret'));
    }

    public function refund(Payment $payment): GatewayPayment
    {
        $reference = (string) $payment->gateway_reference;
        $this->put($reference, ['status' => 'refunded', 'message' => 'REFUNDED'] + $this->row($reference));

        return $this->fetch($reference);
    }

    public function markPaid(string $reference): void
    {
        $this->put($reference, ['status' => 'paid', 'payment_id' => 'fake_pay_'.Str::random(12), 'message' => 'APPROVED'] + $this->row($reference));
    }

    public function markFailed(string $reference): void
    {
        $this->put($reference, ['status' => 'failed', 'payment_id' => 'fake_pay_'.Str::random(12), 'message' => 'DECLINED'] + $this->row($reference));
    }

    public function returnUrl(string $reference): string
    {
        return (string) $this->row($reference)['return_url'];
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $reference): array
    {
        $row = Cache::get(self::PREFIX.$reference);

        if (! is_array($row)) {
            throw new RuntimeException('Unknown fake payment: '.$reference);
        }

        return $row;
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function put(string $reference, array $row): void
    {
        Cache::put(self::PREFIX.$reference, $row, now()->addDay());
    }
}
