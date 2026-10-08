<?php

namespace App\Actions\Payments;

use App\Enums\ActivityEvent;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Support\Payments\GatewayPayment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * اعتماد نتيجة من البوابة (إشعار أو استعلام) على السجل: داخل معاملة بقفل على العملية، فالإشعار
 * المكرر (بمعرّفه) والرجوع المتزامن لا يضاعفان شيئاً. والمبلغ والعملة يُطابقان السجل وإلا سُجّلت
 * مخالفة في السجلين المالي والأمني ولم تُعتمد، وكذلك الاسترداد الجزئي من لوحة البوابة.
 *
 * الانتقالات (الفاتورة تقبل أكثر من محاولة دفع):
 * - «مدفوعة» من معلّقة أو فاشلة متى قالت البوابة إن الفاتورة دُفعت، برقم إيصال وبريد إيصال واحد.
 * - فشل محاولة واحدة (إشعار دفعة) يُقيَّد حدثاً وتبقى العملية معلّقة، لأن المتبرع قد ينجح في المحاولة
 *   التالية على الفاتورة نفسها. وفشل الفاتورة نفسها (منتهية أو ملغاة، من الاستعلام) يُنهيها «فاشلة».
 * - «مستردة» من مدفوعة.
 * - إشعار صحيح لا يغيّر الحالة (مدفوع لسجل مدفوع أو مسترد مثلاً) يُقيَّد «أُهمل» ولا يمس السجل،
 *   فلا يستبدل إشعار متأخر عن محاولة فاشلة رقم الدفعة التي بها يُسترد المبلغ.
 */
class RecordPaymentOutcome
{
    public function __construct(private readonly SendPaymentReceipt $receipt) {}

    public function handle(Payment $payment, GatewayPayment $result, ?string $eventId = null): Payment
    {
        $receipt = false;

        $payment = DB::transaction(function () use ($payment, $result, $eventId, &$receipt): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($eventId !== null && PaymentEvent::query()->where('gateway_event_id', $eventId)->exists()) {
                return $locked;
            }

            if ($result->amount !== $locked->amount || $result->currency !== $locked->currency) {
                return $this->mismatch($locked, $result, $eventId, 'amount or currency');
            }

            // استرداد جزئي من لوحة البوابة: لا يُعلَّم «مستردة»، ويُقيَّد مخالفة حتى يُحسم يدوياً
            if ($result->status === PaymentStatus::Refunded && $result->refunded !== null && $result->refunded < $locked->amount) {
                return $this->mismatch($locked, $result, $eventId, 'partial refund');
            }

            $open = in_array($locked->status, [PaymentStatus::Pending, PaymentStatus::Failed], true);

            if ($result->status === PaymentStatus::Paid && $open) {
                $this->apply($locked, $result);
                $locked->status = PaymentStatus::Paid;
                $locked->paid_at = now();
                $locked->receipt_number = self::receiptNumber();
                $this->event($locked, PaymentEventType::Paid, $result, $eventId);
                $receipt = true;
            } elseif ($result->status === PaymentStatus::Failed && $locked->status === PaymentStatus::Pending) {
                $this->apply($locked, $result);

                if (! $result->attempt) {
                    $locked->status = PaymentStatus::Failed;
                }

                $this->event($locked, PaymentEventType::Failed, $result, $eventId);
            } elseif ($result->status === PaymentStatus::Refunded && $locked->status === PaymentStatus::Paid) {
                // رقم الدفعة المدفوعة يبقى (المشغّل يحرسه بعد الاكتمال)، والرسالة وحدها تتحدث
                $this->apply($locked, $result, withPaymentId: false);
                $locked->status = PaymentStatus::Refunded;
                $locked->refunded_at ??= now();
                $this->event($locked, PaymentEventType::Refunded, $result, $eventId);
            } elseif ($eventId !== null && $result->status !== PaymentStatus::Pending) {
                $this->event($locked, PaymentEventType::Ignored, $result, $eventId);
            }

            $locked->save();

            return $locked;
        });

        if ($receipt) {
            $this->receipt->handle($payment);
        }

        return $payment;
    }

    /**
     * رقم الدفعة ورسالة البوابة تُحفظ مع تغيّر الحالة وحده.
     */
    private function apply(Payment $payment, GatewayPayment $result, bool $withPaymentId = true): void
    {
        if ($withPaymentId && $result->paymentId !== null) {
            $payment->gateway_payment_id = $result->paymentId;
        }

        if ($result->message !== null) {
            $payment->gateway_message = Str::limit($result->message, 250, '');
        }
    }

    /**
     * مال وصل لا يطابق سجله: حدث في السجل المالي، وقيد في السجل الأمني، وتبليغ لتنبيهات الإنتاج.
     */
    private function mismatch(Payment $payment, GatewayPayment $result, ?string $eventId, string $reason): Payment
    {
        $this->event($payment, PaymentEventType::Mismatch, $result, $eventId);

        activity('security')->performedOn($payment)
            ->event(ActivityEvent::PaymentMismatch->value)
            ->withProperties(['reason' => $reason, 'amount' => $result->amount, 'currency' => $result->currency, 'refunded' => $result->refunded])
            ->log(ActivityEvent::PaymentMismatch->value);

        report(new RuntimeException("Payment {$payment->uuid}: gateway result does not match the record ({$reason})."));

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
                'refunded' => $result->refunded,
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
