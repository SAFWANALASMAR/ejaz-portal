<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Enums\ActivityEvent;
use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * إشعار البوابة: يُرفض ما لم يصح توقيعه (بلا أثر في السجل المالي، ويُقيَّد الرفض في السجل الأمني)،
 * ويُطابق بالعملية بمرجعها، ثم يُعتمد بمعرّف الإشعار فلا يُعالج مرتين.
 *
 * @throws AuthorizationException توقيع غير صحيح
 * @throws ModelNotFoundException مرجع لا يقابله سجل
 */
class HandlePaymentWebhook
{
    private const LOGGED_REJECTIONS_PER_MINUTE = 5;

    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentOutcome $record,
    ) {}

    public function handle(Request $request): Payment
    {
        $event = $this->gateway->webhook($request);

        if ($event === null) {
            // يُقيَّد الرفض في السجل الأمني بحد لكل عنوان، فلا يملأ مهاجم سجل العمليات بآلاف الصفوف
            RateLimiter::attempt('webhook-rejected:'.$request->ip(), self::LOGGED_REJECTIONS_PER_MINUTE, fn () => activity('security')
                ->event(ActivityEvent::PaymentWebhookRejected->value)
                ->withProperties(['ip' => $request->ip()])
                ->log(ActivityEvent::PaymentWebhookRejected->value), 60);

            throw new AuthorizationException;
        }

        $payment = Payment::query()->where('gateway_reference', $event->payment->reference)->firstOrFail();

        return $this->record->handle($payment, $event->payment, $event->id);
    }
}
