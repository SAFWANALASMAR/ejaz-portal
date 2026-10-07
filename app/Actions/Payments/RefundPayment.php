<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ActivityEvent;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * استرداد كامل لعملية مدفوعة: لمن له payments.refund (المشرف العام) بسبب إلزامي، يُنفَّذ عند البوابة
 * ثم يُعتمد في السجل ويُقيَّد في سجل العمليات. وشروط آلية الاسترداد لدى الجمعية تُضاف حين تُحدَّد
 * (docs/open-questions.md).
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

        if ($payment->status !== PaymentStatus::Paid || $payment->gateway_reference === null) {
            throw ValidationException::withMessages(['reason' => __('payments.refund.not_paid')]);
        }

        $payment->refund_reason = $reason;
        $payment->refunded_by = $actor->id;
        $payment->save();

        $refunded = $this->record->handle($payment, $this->gateway->refund($payment));

        activity('payments')->performedOn($refunded)->causedBy($actor)
            ->event(ActivityEvent::PaymentRefunded->value)
            ->withProperties(['reason' => $reason, 'amount' => $refunded->amount])
            ->log(ActivityEvent::PaymentRefunded->value);

        return $refunded;
    }
}
