<?php

use App\Actions\Payments\StartDonation;
use App\Contracts\PaymentGateway;
use App\Enums\PaymentEventType;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Providers\AppServiceProvider;
use App\Services\Payments\FakePaymentGateway;
use App\Support\Payments\GatewayPayment;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Validation\ValidationException;
use Mockery\MockInterface;

beforeEach(function () {
    Setting::put('donations', ['enabled' => true, 'intro' => 'نص تجريبي', 'amounts' => [50, 100]]);
});

it('hides the donation page until it is enabled from the panel', function () {
    Setting::put('donations', ['enabled' => false]);

    $this->get('/donate')->assertNotFound();
    $this->post('/donate', ['amount' => '100', 'email' => 'donor@example.test'])->assertNotFound();
});

it('shows the donation form with the suggested amounts from the panel', function () {
    $this->get('/donate')->assertOk()
        ->assertSee(__('payments.donate.title'))
        ->assertSee('نص تجريبي')
        ->assertSee('50')
        ->assertSee('100');
});

it('creates a pending payment with the amount from the server and sends the donor to the gateway', function () {
    $response = $this->post('/donate', ['amount' => '١٥٠٫٥٠', 'email' => 'donor@example.test', 'name' => 'متبرع']);

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->purpose)->toBe(PaymentPurpose::Donation)
        ->and($payment->amount)->toBe(15050)
        ->and($payment->currency)->toBe('SAR')
        ->and($payment->user_id)->toBeNull()
        ->and($payment->donor_email)->toBe('donor@example.test')
        ->and($payment->donor_name)->toBe('متبرع')
        ->and($payment->gateway)->toBe('fake')
        ->and($payment->gateway_reference)->not->toBeNull()
        ->and($payment->events()->pluck('type')->all())->toBe([PaymentEventType::Started]);

    /** @var FakePaymentGateway $gateway */
    $gateway = app(PaymentGateway::class);
    $response->assertRedirect($gateway->fetch($payment->gateway_reference)->checkoutUrl);

    // المبلغ عند البوابة هو مبلغ السجل لا ما قد يُرسله المتصفح لاحقاً
    expect($gateway->fetch($payment->gateway_reference)->amount)->toBe(15050);
});

it('uses the account of a signed-in donor for the receipt', function () {
    $user = User::factory()->create(['email' => 'member@example.test']);

    $this->actingAs($user)->post('/donate', ['amount' => '20'])->assertRedirect();

    $payment = Payment::sole();

    expect($payment->user_id)->toBe($user->id)
        ->and($payment->donor_email)->toBe('member@example.test')
        ->and($payment->donor_name)->toBe($user->name);
});

it('rejects a wrong amount, a missing email for guests, and a bad email', function (array $input, string $field) {
    $this->from('/donate')->post('/donate', $input)->assertRedirect('/donate')->assertSessionHasErrors($field);

    expect(Payment::count())->toBe(0);
})->with([
    'not a number' => [['amount' => 'كثير', 'email' => 'donor@example.test'], 'amount'],
    'below the minimum' => [['amount' => '0.5', 'email' => 'donor@example.test'], 'amount'],
    'above the technical cap' => [['amount' => '1000000000', 'email' => 'donor@example.test'], 'amount'],
    'three decimals' => [['amount' => '10.123', 'email' => 'donor@example.test'], 'amount'],
    'negative' => [['amount' => '-10', 'email' => 'donor@example.test'], 'amount'],
    'guest without email' => [['amount' => '10'], 'email'],
    'bad email' => [['amount' => '10', 'email' => 'not-an-email'], 'email'],
]);

it('refuses to start a donation from the action when donations are disabled', function () {
    Setting::put('donations', ['enabled' => false]);

    expect(fn () => app(StartDonation::class)->handle(null, ['amount' => '10', 'email' => 'donor@example.test']))
        ->toThrow(AuthorizationException::class);
});

it('validates the amount in the action itself', function () {
    expect(fn () => app(StartDonation::class)->handle(null, ['amount' => '0', 'email' => 'donor@example.test']))
        ->toThrow(ValidationException::class);
});

it('offers the local fake checkout only with the fake driver, in the local and testing environments, for a pending payment', function () {
    $this->post('/donate', ['amount' => '10', 'email' => 'donor@example.test']);
    $payment = Payment::sole();

    $this->get(route('payments.fake-checkout', $payment))->assertOk()->assertSee(__('payments.fake.pay'));

    config(['payments.driver' => 'moyasar']);
    $this->get(route('payments.fake-checkout', $payment))->assertNotFound();
    $this->post(route('payments.fake-checkout.pay', $payment))->assertNotFound();
    config(['payments.driver' => 'fake']);

    // بيئة تجريبية عامة: لا صفحة وهمية ولو كان المشغّل fake
    $this->app->detectEnvironment(fn (): string => 'staging');
    $this->get(route('payments.fake-checkout', $payment))->assertNotFound();
    $this->app->detectEnvironment(fn (): string => 'testing');

    // بعد الدفع لا تُفتح الصفحة ثانية
    $this->post(route('payments.fake-checkout.pay', $payment))->assertRedirect(route('payments.return', $payment));
    $this->get(route('payments.return', $payment))->assertOk()->assertSee(__('payments.statuses.paid'));
    $this->get(route('payments.fake-checkout', $payment))->assertNotFound();
});

it('reads thousands separators and decimal marks the way people type them', function (string $input, int $halalas) {
    $this->post('/donate', ['amount' => $input, 'email' => 'donor@example.test'])->assertRedirect();

    expect(Payment::sole()->amount)->toBe($halalas);
})->with([
    'latin thousands' => ['1,000', 100000],
    'thousands and decimals' => ['1,000.50', 100050],
    'comma as a decimal mark' => ['10,5', 1050],
    'arabic thousands and decimal mark' => ['١٬٢٥٠٫٧٥', 125075],
]);

it('lets an account with a long name donate, trimming the name on the receipt', function () {
    $user = User::factory()->create(['name' => str_repeat('م', 120)]);

    $this->actingAs($user)->post('/donate', ['amount' => '20'])->assertRedirect();

    expect(mb_strlen((string) Payment::sole()->donor_name))->toBe(100);
});

it('treats a gateway answer without a reference or with a non-https checkout link as a gateway error', function (GatewayPayment $answer) {
    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('start')->once()->andReturn($answer));

    $this->from('/donate')->post('/donate', ['amount' => '10', 'email' => 'donor@example.test'])->assertRedirect('/donate')->assertSessionHasErrors('amount');

    expect(Payment::sole()->gateway_reference)->toBeNull()
        ->and(Payment::sole()->events()->where('type', PaymentEventType::GatewayError)->count())->toBe(1);
})->with([
    'no reference' => [new GatewayPayment('', PaymentStatus::Pending, 1000, 'SAR', checkoutUrl: 'https://pay.example/inv')],
    'javascript link' => [new GatewayPayment('inv_1', PaymentStatus::Pending, 1000, 'SAR', checkoutUrl: 'javascript:alert(1)')],
]);

it('refuses the fake driver at boot in production', function () {
    $this->app->detectEnvironment(fn (): string => 'production');
    config(['payments.driver' => 'fake']);

    expect(fn () => (new AppServiceProvider($this->app))->boot())->toThrow(RuntimeException::class);

    $this->app->detectEnvironment(fn (): string => 'testing');
});

it('caps donation starts per hour for the whole portal', function () {
    config(['payments.donation.per_hour' => 1]);

    $this->post('/donate', ['amount' => '10', 'email' => 'donor@example.test'])->assertRedirect();
    $this->from('/donate')->post('/donate', ['amount' => '10', 'email' => 'other@example.test'])->assertRedirect('/donate')->assertSessionHasErrors('amount');

    expect(Payment::count())->toBe(1);
});

it('tells the donor when the gateway is down, keeps the record pending without a reference, and logs the error', function () {
    $this->mock(PaymentGateway::class, fn (MockInterface $mock) => $mock->shouldReceive('start')->once()->andThrow(new ConnectionException('gateway down')));

    $this->from('/donate')->post('/donate', ['amount' => '10', 'email' => 'donor@example.test'])->assertRedirect('/donate')->assertSessionHasErrors('amount');

    $payment = Payment::sole();

    expect($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->gateway_reference)->toBeNull()
        ->and($payment->events()->pluck('type')->all())->toBe([PaymentEventType::Started, PaymentEventType::GatewayError]);
});
