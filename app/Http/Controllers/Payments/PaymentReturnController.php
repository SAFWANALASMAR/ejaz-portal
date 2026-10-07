<?php

namespace App\Http\Controllers\Payments;

use App\Actions\Payments\SyncPaymentWithGateway;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Setting;
use Illuminate\Contracts\View\View;

/**
 * رجوع المتصفح من صفحة الدفع: تُقرأ الحالة من البوابة نفسها (لا من معاملات الرابط) ثم تُعرض.
 * الصفحة بمعرّف العملية العشوائي، وتعرض الحالة والمبلغ ورقم الإيصال دون بيانات المتبرع.
 */
class PaymentReturnController extends Controller
{
    public function __invoke(Payment $payment, SyncPaymentWithGateway $sync): View
    {
        return view('payments.show', [
            'payment' => $sync->handle($payment),
            // زر «المحاولة مرة أخرى» لا يظهر إن عُطّل التبرع من اللوحة
            'donationsEnabled' => (bool) (Setting::group('donations')['enabled'] ?? false),
        ]);
    }
}
