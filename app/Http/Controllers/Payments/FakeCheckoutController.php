<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\FakePaymentGateway;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * صفحة الدفع التجريبية للبوابة الوهمية: في البيئة المحلية وبالمشغّل fake فقط، وإلا 404.
 * زر «ادفع» يعلّم العملية مدفوعة عند البوابة الوهمية ثم يعيد إلى صفحة الرجوع، التي تستعلم منها.
 */
class FakeCheckoutController extends Controller
{
    public function show(Payment $payment): View
    {
        $gateway = $this->gateway($payment);

        return view('payments.fake-checkout', ['payment' => $payment, 'result' => $gateway->fetch((string) $payment->gateway_reference)]);
    }

    public function pay(Payment $payment): RedirectResponse
    {
        $gateway = $this->gateway($payment);
        $gateway->markPaid((string) $payment->gateway_reference);

        return redirect()->to($gateway->returnUrl((string) $payment->gateway_reference));
    }

    public function fail(Payment $payment): RedirectResponse
    {
        $gateway = $this->gateway($payment);
        $gateway->markFailed((string) $payment->gateway_reference);

        return redirect()->to($gateway->returnUrl((string) $payment->gateway_reference));
    }

    private function gateway(Payment $payment): FakePaymentGateway
    {
        abort_unless(config('payments.driver') === 'fake' && ! app()->isProduction() && $payment->gateway_reference !== null, 404);

        return app(FakePaymentGateway::class);
    }
}
