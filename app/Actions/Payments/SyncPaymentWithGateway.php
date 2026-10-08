<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

/**
 * قراءة حالة العملية من البوابة واعتمادها: عند رجوع المتصفح لا يُعتمد ما في رابطه بل ما تقوله
 * البوابة. والعملية المكتملة (مدفوعة أو فاشلة أو مستردة) لا تُستعلم، فلا يولّد حامل الرابط طلبات
 * إلى البوابة؛ والاسترداد يصل بالإشعار أو من اللوحة. ويستثني الأمر المجدول «الفاشلة» التي ما زالت
 * فاتورتها في عمرها، لأن البوابة قد تقبل دفعها بعد ذلك. وإن تعطلت البوابة عُرضت الحالة المخزنة.
 */
class SyncPaymentWithGateway
{
    /**
     * استعلامات في الدقيقة لكل عملية، فلا يولّد حامل رابط عملية معلّقة طلبات متتابعة إلى البوابة.
     */
    private const PER_MINUTE = 6;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentOutcome $record,
    ) {}

    public function handle(Payment $payment, bool $evenIfFailed = false): Payment
    {
        if ($payment->gateway_reference === null) {
            return $payment;
        }

        if ($payment->status->isFinal() && ! ($evenIfFailed && $payment->status === PaymentStatus::Failed)) {
            return $payment;
        }

        if (RateLimiter::tooManyAttempts('payment-sync:'.$payment->uuid, self::PER_MINUTE)) {
            return $payment;
        }

        RateLimiter::hit('payment-sync:'.$payment->uuid, 60);

        try {
            $result = $this->gateway->fetch($payment->gateway_reference);
        } catch (ConnectionException|RequestException|RuntimeException $exception) {
            Log::warning('Could not read a payment from the gateway', ['payment' => $payment->uuid, 'error' => $exception::class]);

            return $payment;
        }

        return $this->record->handle($payment, $result);
    }
}
