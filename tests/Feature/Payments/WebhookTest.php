<?php

use App\Actions\Payments\RecordPaymentOutcome;
use App\Contracts\PaymentGateway;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Notifications\PaymentReceipt;
use App\Services\Payments\FakePaymentGateway;
use App\Support\Payments\GatewayPayment;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

beforeEach(function () {
    Notification::fake();
    config(['payments.webhook_secret' => 'test-webhook-secret']);

    $this->payment = Payment::factory()->create(['amount' => 10000]);

    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $this->payment->forceFill(['gateway_reference' => $gateway->start($this->payment, 'http://localhost/return')->reference])->save();
});

/**
 * إشعار بصيغة ميسر: الحدث ورمزه السري وبيانات العملية بفاتورتها.
 *
 * @param  array<string, mixed>  $overrides
 */
function webhook(Payment $payment, array $overrides = [], string $secret = 'test-webhook-secret', string $eventId = 'evt_1'): TestResponse
{
    return test()->postJson('/webhooks/payments', [
        'id' => $eventId,
        'type' => 'payment_paid',
        'secret_token' => $secret,
        'data' => [
            'id' => 'pay_1',
            'invoice_id' => $payment->gateway_reference,
            'status' => 'paid',
            'amount' => $payment->amount,
            'currency' => $payment->currency,
            'source' => ['type' => 'creditcard', 'company' => 'mada', 'number' => '4111-11XX-XXXX-1111', 'message' => 'APPROVED'],
            ...$overrides,
        ],
    ]);
}

it('marks the payment paid once from a signed notification, with one receipt', function () {
    webhook($this->payment)->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->paid_at)->not->toBeNull()
        ->and($payment->receipt_number)->toMatch('/^R-\d{4}-[A-Z0-9]{8}$/')
        ->and($payment->gateway_payment_id)->toBe('pay_1')
        ->and($payment->events()->where('type', PaymentEventType::Paid)->count())->toBe(1);

    Notification::assertSentOnDemand(PaymentReceipt::class, fn (PaymentReceipt $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['mail'] === $payment->donor_email && $notification->payment->is($payment));
    Notification::assertCount(1);

    // لا بيانات بطاقة في السجل المالي
    $ledger = json_encode($payment->events()->get()->pluck('payload'));
    expect($ledger)->not->toContain('4111')->not->toContain('mada');
});

it('ignores a repeated notification and a race between the webhook and the browser return', function () {
    webhook($this->payment)->assertOk();
    webhook($this->payment)->assertOk();
    webhook($this->payment, eventId: 'evt_2')->assertOk();

    // رجوع المتصفح بعد الإشعار يستعلم من البوابة ولا يضاعف
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $gateway->markPaid($this->payment->gateway_reference);
    $this->get(route('payments.return', $this->payment))->assertOk()->assertSee(__('payments.statuses.paid'));

    expect(PaymentEvent::where('gateway_event_id', 'evt_1')->count())->toBe(1)
        ->and($this->payment->fresh()->events()->where('type', PaymentEventType::Paid)->count())->toBe(1);

    Notification::assertCount(1);
});

it('rejects a notification with a wrong or missing secret and leaves the payment untouched', function () {
    webhook($this->payment, secret: 'wrong')->assertForbidden();
    $this->postJson('/webhooks/payments', ['id' => 'x', 'type' => 'payment_paid', 'data' => []])->assertForbidden();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentEvent::where('type', PaymentEventType::Paid)->exists())->toBeFalse();

    Notification::assertNothingSent();
});

it('does not accept a paid notification whose amount or currency differs from the record', function () {
    webhook($this->payment, ['amount' => 100])->assertOk();
    webhook($this->payment, ['currency' => 'USD'], eventId: 'evt_2')->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->events()->where('type', PaymentEventType::Mismatch)->count())->toBe(2);

    Notification::assertNothingSent();
});

it('answers 404 for a notification about an unknown payment', function () {
    webhook($this->payment, ['invoice_id' => 'unknown'])->assertNotFound();
});

it('records a failure and a refund from the gateway without a receipt', function () {
    webhook($this->payment, ['status' => 'failed', 'source' => ['message' => 'DECLINED']])->assertOk();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed)
        ->and($this->payment->fresh()->gateway_message)->toBe('DECLINED');
    Notification::assertNothingSent();

    // الحالة النهائية لا تتغير بإشعار لاحق
    webhook($this->payment, eventId: 'evt_2')->assertOk();
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    $paid = Payment::factory()->paid()->create();
    app(RecordPaymentOutcome::class)->handle($paid, new GatewayPayment($paid->gateway_reference, PaymentStatus::Refunded, $paid->amount, $paid->currency, paymentId: 'pay_9'));

    expect($paid->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($paid->fresh()->refunded_at)->not->toBeNull();
});

it('verifies the result on the browser return from the gateway, not from the URL', function () {
    $this->get(route('payments.return', $this->payment).'?status=paid')->assertOk()->assertSee(__('payments.statuses.pending'));
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);

    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $gateway->markPaid($this->payment->gateway_reference);

    $this->get(route('payments.return', $this->payment))->assertOk()
        ->assertSee(__('payments.statuses.paid'))
        ->assertSee($this->payment->fresh()->receipt_number)
        ->assertDontSee($this->payment->donor_email);
    Notification::assertCount(1);
});
