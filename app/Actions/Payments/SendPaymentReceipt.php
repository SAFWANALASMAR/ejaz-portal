<?php

namespace App\Actions\Payments;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Notifications\PaymentReceipt;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * الإيصال إلى بريد المتبرع، مرة واحدة: يُعلَّم وقت الإرسال بعد الدفع إلى الطابور. وإن تعذر الطابور
 * لحظة اعتماد الدفع يُبلَّغ الخطأ ويبقى الوقت فارغاً، فيعيد الأمر المجدول الإرسال.
 */
class SendPaymentReceipt
{
    public function handle(Payment $payment): void
    {
        if ($payment->status !== PaymentStatus::Paid || $payment->donor_email === null || $payment->receipt_sent_at !== null) {
            return;
        }

        try {
            Notification::route('mail', $payment->donor_email)->notify(new PaymentReceipt($payment));
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        $payment->forceFill(['receipt_sent_at' => now()])->save();
    }
}
