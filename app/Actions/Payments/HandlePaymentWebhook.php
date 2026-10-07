<?php

namespace App\Actions\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * إشعار البوابة: يُرفض ما لم يصح توقيعه (بلا أثر في السجل)، ويُطابق بالعملية بمرجعها، ثم يُعتمد
 * بمعرّف الإشعار فلا يُعالج مرتين.
 *
 * @throws AuthorizationException توقيع غير صحيح
 * @throws ModelNotFoundException مرجع لا يقابله سجل
 */
class HandlePaymentWebhook
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly RecordPaymentOutcome $record,
    ) {}

    public function handle(Request $request): Payment
    {
        $event = $this->gateway->webhook($request);

        if ($event === null) {
            Log::warning('Rejected a payment webhook with a bad signature', ['ip' => $request->ip()]);

            throw new AuthorizationException;
        }

        $payment = Payment::query()->where('gateway_reference', $event->payment->reference)->firstOrFail();

        return $this->record->handle($payment, $event->payment, $event->id);
    }
}
