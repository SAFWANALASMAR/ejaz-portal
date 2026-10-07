<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ActivityEvent;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * استرداد كامل لعملية مدفوعة: لمن له payments.refund (المشرف العام) بسبب إلزامي. يُفحص السجل بقفل
 * داخل معاملة (فلا تنفَّذ ضغطتان متزامنتان مرتين)، ثم البوابة، ثم يُحفظ السبب والمنفّذ ويُعتمد
 * الاسترداد عند نجاحها وحده. وفشل البوابة يُقيَّد حدثاً «فشل الاسترداد» وتبقى العملية مدفوعة بلا
 * سبب استرداد. وشروط آلية الاسترداد لدى الجمعية تُضاف حين تُحدَّد (docs/open-questions.md).
 */
class RefundPayment
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentOutcome $record,
    ) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Payment $payment, string $reason): Payment
    {
        if (! $actor->can('refund', $payment)) {
            throw new AuthorizationException;
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('payments.refund.reason_rule')]);
        }

        $failure = null;

        $refunded = DB::transaction(function () use ($actor, $payment, $reason, &$failure): Payment {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentStatus::Paid || $locked->gateway_reference === null) {
                throw ValidationException::withMessages(['reason' => __('payments.refund.not_paid')]);
            }

            try {
                $result = $this->gateway->refund($locked);
            } catch (ConnectionException|RequestException|RuntimeException $exception) {
                report($exception);
                $failure = $exception::class;

                return $locked;
            }

            if ($result->status !== PaymentStatus::Refunded) {
                $failure = 'gateway status: '.$result->status->value;

                return $locked;
            }

            $locked->refund_reason = $reason;
            $locked->refunded_by = $actor->id;
            $locked->save();

            $refunded = $this->record->handle($locked, $result);

            activity('payments')->performedOn($refunded)->causedBy($actor)
                ->event(ActivityEvent::PaymentRefunded->value)
                ->withProperties(['reason' => $reason, 'amount' => $refunded->amount])
                ->log(ActivityEvent::PaymentRefunded->value);

            return $refunded;
        });

        if ($failure !== null) {
            // خارج المعاملة حتى يبقى الحدث بعد رفض الطلب
            $refunded->events()->create(['type' => PaymentEventType::RefundFailed, 'payload' => ['message' => $failure]]);

            throw ValidationException::withMessages(['reason' => __('payments.refund.failed')]);
        }

        return $refunded;
    }
}
