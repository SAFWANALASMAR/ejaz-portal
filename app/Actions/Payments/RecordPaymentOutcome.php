<?php

namespace App\Actions\Payments;

use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Notifications\PaymentReceipt;
use App\Support\Payments\GatewayPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

/**
 * اعتماد نتيجة من البوابة (إشعار أو استعلام) على السجل: داخل معاملة بقفل على العملية، فالإشعار
 * المكرر (بمعرّفه) والرجوع المتزامن لا يضاعفان شيئاً. والمبلغ والعملة يُطابقان السجل وإلا سُجّلت
 * مخالفة ولم تُعتمد.
 *
 * الانتقالات (الفاتورة تقبل أكثر من محاولة دفع):
 * - «مدفوعة» من معلّقة أو فاشلة متى قالت البوابة إن الفاتورة دُفعت، برقم إيصال وبريد إيصال واحد.
 * - فشل محاولة واحدة (إشعار دفعة) يُقيَّد حدثاً وتبقى العملية معلّقة، لأن المتبرع قد ينجح في المحاولة
 *   التالية على الفاتورة نفسها. وفشل الفاتورة نفسها (منتهية أو ملغاة، من الاستعلام) يُنهيها «فاشلة».
 * - «مستردة» من مدفوعة.
 * - إشعار صحيح لا يغيّر الحالة (مدفوع لسجل مدفوع أو مسترد مثلاً) يُقيَّد «أُهمل» ولا يضيع بصمت.
 */
class RecordPaymentOutcome
{
    public function handle(Payment $payment, GatewayPayment $result, ?string $eventId = null): Payment
    {
        $receipt = false;

        $payment = DB::transaction(function () use ($payment, $result, $eventId, &$receipt): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($eventId !== null && PaymentEvent::query()->where('gateway_event_id', $eventId)->exists()) {
                return $locked;
            }

            if ($result->amount !== $locked->amount || $result->currency !== $locked->currency) {
                $this->event($locked, PaymentEventType::Mismatch, $result, $eventId);
                Log::warning('Payment result does not match the record', ['payment' => $locked->uuid, 'amount' => $result->amount, 'currency' => $result->currency]);

                return $locked;
            }

            if ($result->paymentId !== null) {
                $locked->gateway_payment_id = $result->paymentId;
            }

            if ($result->message !== null) {
                $locked->gateway_message = Str::limit($result->message, 250, '');
            }

            $open = in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Failed], true);

            if ($result->status === PaymentStatus::Paid && $open) {
                $locked->status = PaymentStatus::Paid;
                $locked->paid_at = now();
                $locked->receipt_number = self::receiptNumber();
                $this->event($locked, PaymentEventType::Paid, $result, $eventId);
                $receipt = true;
            } elseif ($result->status === PaymentStatus::Failed && $locked->status === PaymentStatus::Pending) {
                if (! $result->attempt) {
                    $locked->status = PaymentStatus::Failed;
                }

                $this->event($locked, PaymentEventType::Failed, $result, $eventId);
            } elseif ($result->status === PaymentStatus::Refunded && $locked->status === PaymentStatus::Paid) {
                $locked->status = PaymentStatus::Refunded;
                $locked->refunded_at ??= now();
                $this->event($locked, PaymentEventType::Refunded, $result, $eventId);
            } elseif ($eventId !== null && $result->status !== PaymentStatus::Pending) {
                $this->event($locked, PaymentEventType::Ignored, $result, $eventId);
            }

            $locked->save();

            return $locked;
        });

        if ($receipt && $payment->donor_email !== null) {
            Notification::route('mail', $payment->donor_email)->notify(new PaymentReceipt($payment));
        }

        return $payment;
    }

    private function event(Payment $payment, PaymentEventType $type, GatewayPayment $result, ?string $eventId): void
    {
        $payment->events()->create([
            'type' => $type,
            'gateway_event_id' => $eventId,
            'payload' => [
                'status' => $result->status->value,
                'amount' => $result->amount,
                'currency' => $result->currency,
                'payment_id' => $result->paymentId,
                'message' => $result->message === null ? null : Str::limit($result->message, 250, ''),
            ],
        ]);
    }

    /**
     * رقم إيصال عشوائي لا يُعدّ ولا يُخمَّن، وفريد بقيد قاعدة البيانات.
     */
    private static function receiptNumber(): string
    {
        return 'R-'.now()->year.'-'.Str::upper(Str::random(8));
    }
}
