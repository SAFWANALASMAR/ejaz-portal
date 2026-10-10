<?php

use App\Actions\Payments\RecordPaymentOutcome;
use App\Contracts\PaymentGateway;
use App\Enums\ActivityEvent;
use App\Enums\PaymentEventType;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Notifications\PaymentReceipt;
use App\Services\Payments\FakePaymentGateway;
use App\Support\Payments\GatewayPayment;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Mockery\MockInterface;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    Notification::fake();
    config(['payments.webhook_secret' => 'test-webhook-secret']);

    $this->payment = Payment::factory()->create(['amount' => 10000]);

    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $this->payment->forceFill(['gateway_reference' => $gateway->start($this->payment, 'http://localhost/return')->reference])->save();
});

/**
 * إشعار بصيغة ميسر: الحدث ورمزه السري وبيانات الدفعة بفاتورتها.
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

    // رجوع المتصفح بعد الإشعار لا يضاعف
    $this->get(route('payments.return', $this->payment))->assertOk()->assertSee(__('payments.statuses.paid'));

    expect(PaymentEvent::where('gateway_event_id', 'evt_1')->count())->toBe(1)
        ->and($this->payment->fresh()->events()->where('type', PaymentEventType::Paid)->count())->toBe(1);

    Notification::assertCount(1);
});

it('rejects a notification with a wrong, missing or query-string secret, leaves the payment untouched, and logs the rejection', function () {
    webhook($this->payment, secret: 'wrong')->assertForbidden();
    $this->postJson('/webhooks/payments', ['id' => 'x', 'type' => 'payment_paid', 'data' => []])->assertForbidden();
    // الرمز من جسم الطلب وحده، لا من سلسلة الاستعلام
    $this->postJson('/webhooks/payments?secret_token=test-webhook-secret', ['id' => 'q', 'type' => 'payment_paid', 'data' => ['invoice_id' => $this->payment->gateway_reference, 'status' => 'paid', 'amount' => 10000, 'currency' => 'SAR']])->assertForbidden();

    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending)
        ->and(PaymentEvent::where('type', PaymentEventType::Paid)->exists())->toBeFalse()
        ->and(Activity::where('event', ActivityEvent::PaymentWebhookRejected->value)->count())->toBe(3);

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

it('answers 404 for an unknown payment, 400 for a malformed notice, and 2xx for an unsupported event with a valid token', function () {
    webhook($this->payment, ['invoice_id' => 'unknown'])->assertNotFound();
    webhook($this->payment, ['invoice_id' => str_repeat('i', 101)])->assertNotFound();
    webhook($this->payment, eventId: str_repeat('e', 101))->assertStatus(400);
    // حدث لا نعالجه (توثيق بطاقة): يُقبل بلا أثر حتى لا تعيد البوابة إرساله
    $this->postJson('/webhooks/payments', ['id' => 'evt_x', 'type' => 'card_auth_failed', 'secret_token' => 'test-webhook-secret', 'data' => []])->assertOk()->assertJson(['ignored' => true]);

    // لا شيء من ذلك توقيع خاطئ، فلا يُقيَّد أمنياً، ولا يمس السجل المالي
    expect(Activity::where('event', ActivityEvent::PaymentWebhookRejected->value)->exists())->toBeFalse()
        ->and($this->payment->fresh()->events()->count())->toBe(0);
});

it('keeps the paid attempt and its message when a late failed notice for another attempt arrives', function () {
    webhook($this->payment, ['id' => 'pay_B'])->assertOk();
    webhook($this->payment, ['id' => 'pay_A', 'status' => 'failed', 'source' => ['message' => 'DECLINED']], eventId: 'evt_2')->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->gateway_payment_id)->toBe('pay_B')
        ->and($payment->gateway_message)->toBe('APPROVED')
        ->and($payment->events()->where('type', PaymentEventType::Ignored)->count())->toBe(1);
});

it('records a partial refund from the gateway as a mismatch and keeps the payment paid', function () {
    $paid = Payment::factory()->paid()->create(['amount' => 10000]);
    app(RecordPaymentOutcome::class)->handle($paid, new GatewayPayment($paid->gateway_reference, PaymentStatus::Refunded, 10000, 'SAR', paymentId: 'pay_9', attempt: true, refunded: 4000), 'evt_partial');

    $paid->refresh();

    expect($paid->status)->toBe(PaymentStatus::Paid)
        ->and($paid->refunded_at)->toBeNull()
        ->and($paid->events()->where('type', PaymentEventType::Mismatch)->count())->toBe(1)
        ->and(Activity::where('event', ActivityEvent::PaymentMismatch->value)->where('subject_id', $paid->id)->exists())->toBeTrue();
});

it('records a failed attempt without closing the invoice, then pays it on the next successful attempt with one receipt', function () {
    webhook($this->payment, ['status' => 'failed', 'source' => ['message' => 'DECLINED']])->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_message)->toBe('DECLINED')
        ->and($payment->events()->where('type', PaymentEventType::Failed)->count())->toBe(1);
    Notification::assertNothingSent();

    webhook($this->payment, eventId: 'evt_2')->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)->and($payment->receipt_number)->not->toBeNull();
    Notification::assertCount(1);
});

it('pays a record the invoice had closed as failed when the gateway later says paid, and books a notice for a settled record as ignored', function () {
    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $gateway->markFailed((string) $this->payment->gateway_reference);

    $this->get(route('payments.return', $this->payment))->assertOk()->assertSee(__('payments.statuses.failed'));
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Failed);

    webhook($this->payment)->assertOk();
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Paid);
    Notification::assertCount(1);

    webhook($this->payment, eventId: 'evt_2')->assertOk();
    webhook($this->payment, ['status' => 'failed'], eventId: 'evt_3')->assertOk();

    $payment = $this->payment->fresh();

    expect($payment->status)->toBe(PaymentStatus::Paid)
        ->and($payment->events()->where('type', PaymentEventType::Paid)->count())->toBe(1)
        ->and($payment->events()->where('type', PaymentEventType::Ignored)->count())->toBe(2);
    Notification::assertCount(1);
});

it('refunds a paid record from the gateway without a receipt', function () {
    $paid = Payment::factory()->paid()->create();
    app(RecordPaymentOutcome::class)->handle($paid, new GatewayPayment($paid->gateway_reference, PaymentStatus::Refunded, $paid->amount, $paid->currency, paymentId: 'pay_9', attempt: true));

    expect($paid->fresh()->status)->toBe(PaymentStatus::Refunded)
        ->and($paid->fresh()->refunded_at)->not->toBeNull()
        // رقم الدفعة المدفوعة يبقى كما هو بعد الاكتمال
        ->and($paid->fresh()->gateway_payment_id)->toBe($paid->gateway_payment_id);
    Notification::assertNothingSent();
});

it('verifies the result on the browser return from the gateway, not from the URL', function () {
    $this->get(route('payments.return', $this->payment).'?status=paid')->assertOk()->assertSee(__('payments.statuses.pending'));
    expect($this->payment->fresh()->status)->toBe(PaymentStatus::Pending);

    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $gateway->markPaid((string) $this->payment->gateway_reference);

    $this->get(route('payments.return', $this->payment))->assertOk()
        ->assertSee(__('payments.statuses.paid'))
        ->assertSee($this->payment->fresh()->receipt_number)
        ->assertDontSee($this->payment->donor_email);
    Notification::assertCount(1);
});

it('does not ask the gateway about a settled payment on return, and shows the stored state when the gateway is down', function () {
    $paid = Payment::factory()->paid()->create();
    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldNotReceive('fetch'));
    $this->get(route('payments.return', $paid))->assertOk()->assertSee(__('payments.statuses.paid'));

    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('fetch')->once()->andThrow(new ConnectionException('gateway down')));
    $this->get(route('payments.return', $this->payment))->assertOk()->assertSee(__('payments.statuses.pending'));
});
