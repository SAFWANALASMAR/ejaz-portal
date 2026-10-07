<?php

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Services\Payments\MoyasarPaymentGateway;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config(['payments.moyasar.secret_key' => 'sk_test_secret', 'payments.webhook_secret' => 'hook-secret']);
    $this->gateway = app(MoyasarPaymentGateway::class);
    $this->payment = Payment::factory()->create(['amount' => 25000]);
});

it('creates an invoice with the amount from the record, the return URL and the secret key', function () {
    Http::fake(['api.moyasar.com/v1/invoices' => Http::response(['id' => 'inv_1', 'status' => 'initiated', 'amount' => 25000, 'currency' => 'SAR', 'url' => 'https://api.moyasar.com/v1/invoices/inv_1/pay', 'payments' => []])]);

    $result = $this->gateway->start($this->payment, 'https://ejaz.test/return');

    expect($result->reference)->toBe('inv_1')
        ->and($result->status)->toBe(PaymentStatus::Pending)
        ->and($result->checkoutUrl)->toBe('https://api.moyasar.com/v1/invoices/inv_1/pay');

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.moyasar.com/v1/invoices'
        && $request->hasHeader('Authorization', 'Basic '.base64_encode('sk_test_secret:'))
        && $request['amount'] === 25000
        && $request['currency'] === 'SAR'
        && $request['success_url'] === 'https://ejaz.test/return'
        && $request['back_url'] === 'https://ejaz.test/return'
        && $request['metadata']['payment'] === $this->payment->uuid);
});

it('reads the invoice state and the payment inside it', function (string $status, PaymentStatus $expected) {
    Http::fake(['api.moyasar.com/v1/invoices/inv_1' => Http::response(['id' => 'inv_1', 'status' => $status, 'amount' => 25000, 'currency' => 'SAR', 'payments' => [['id' => 'pay_1', 'status' => $status, 'source' => ['message' => 'APPROVED']]]])]);

    $result = $this->gateway->fetch('inv_1');

    expect($result->status)->toBe($expected)
        ->and($result->paymentId)->toBe('pay_1')
        ->and($result->amount)->toBe(25000);
})->with([
    ['initiated', PaymentStatus::Pending],
    ['paid', PaymentStatus::Paid],
    ['captured', PaymentStatus::Paid],
    ['failed', PaymentStatus::Failed],
    ['expired', PaymentStatus::Failed],
    ['canceled', PaymentStatus::Failed],
    ['refunded', PaymentStatus::Refunded],
]);

it('accepts a webhook only with the configured secret token', function () {
    $body = ['id' => 'evt_1', 'type' => 'payment_paid', 'secret_token' => 'hook-secret', 'data' => ['id' => 'pay_1', 'invoice_id' => 'inv_1', 'status' => 'paid', 'amount' => 25000, 'currency' => 'SAR', 'source' => ['message' => 'APPROVED', 'number' => '4111-11XX']]];

    $event = $this->gateway->webhook(HttpRequest::create('/webhooks/payments', 'POST', $body));

    expect($event?->id)->toBe('evt_1')
        ->and($event?->payment->reference)->toBe('inv_1')
        ->and($event?->payment->paymentId)->toBe('pay_1')
        ->and($event?->payment->status)->toBe(PaymentStatus::Paid)
        ->and($event?->payment->message)->toBe('APPROVED');

    expect($this->gateway->webhook(HttpRequest::create('/webhooks/payments', 'POST', ['secret_token' => 'wrong'] + $body)))->toBeNull()
        ->and($this->gateway->webhook(HttpRequest::create('/webhooks/payments', 'POST', ['type' => 'card_auth_failed'] + $body)))->toBeNull();

    // بلا رمز مضبوط لا يُقبل إشعار، ولو كان رمزه فارغاً مثله
    config(['payments.webhook_secret' => '']);
    expect(app(MoyasarPaymentGateway::class)->webhook(HttpRequest::create('/webhooks/payments', 'POST', ['secret_token' => ''] + $body)))->toBeNull();
});

it('refunds the full amount of the gateway payment', function () {
    Http::fake(['api.moyasar.com/v1/payments/pay_1/refund' => Http::response(['id' => 'pay_1', 'status' => 'refunded', 'amount' => 25000, 'currency' => 'SAR', 'invoice_id' => 'inv_1', 'refunded' => 25000])]);
    $this->payment->forceFill(['gateway_reference' => 'inv_1', 'gateway_payment_id' => 'pay_1'])->save();

    $result = $this->gateway->refund($this->payment);

    expect($result->status)->toBe(PaymentStatus::Refunded)->and($result->reference)->toBe('inv_1');
    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.moyasar.com/v1/payments/pay_1/refund' && $request->method() === 'POST');
});

it('refuses to run without a secret key', function () {
    config(['payments.moyasar.secret_key' => '']);

    expect(fn () => app(MoyasarPaymentGateway::class)->fetch('inv_1'))->toThrow(RuntimeException::class);
});
