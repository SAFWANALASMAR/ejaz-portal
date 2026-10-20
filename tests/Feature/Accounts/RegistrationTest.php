<?php

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\RegistrationAttemptOnAccount;
use App\Notifications\VerifyEmailNotification;
use App\Support\SaudiPhone;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSmsGateway;

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function registrationData(array $overrides = []): array
{
    return [
        'name' => 'عبدالله محمد',
        'email' => 'Abdullah@Example.test',
        'phone' => '0512345678',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
        ...$overrides,
    ];
}

it('shows the registration form', function () {
    $this->get('/register')->assertOk()->assertSee(__('account.register.title'));
});

it('registers a beneficiary and starts phone verification', function () {
    $this->post('/register', registrationData())->assertRedirect(route('account.phone.verify'));

    $user = User::where('email', 'abdullah@example.test')->firstOrFail();

    expect($user->phone)->toBe('+966512345678')
        ->and($user->privacy_accepted_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([RoleName::Member->value])
        ->and($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertSentTo($user, VerifyEmailNotification::class);
});

it('requires accepting the privacy policy', function () {
    $this->post('/register', registrationData(['privacy' => null]))->assertSessionHasErrors('privacy');

    expect(User::count())->toBe(0);
});

it('rejects invalid input', function (array $overrides, string $field) {
    $this->post('/register', registrationData($overrides))->assertSessionHasErrors($field);
})->with([
    'non-Saudi phone' => [['phone' => '+201012345678'], 'phone'],
    'landline' => [['phone' => '0112345678'], 'phone'],
    'bad email' => [['email' => 'not-an-email'], 'email'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'mismatch' => [['password_confirmation' => 'Something-Else-2026'], 'password'],
    'short name' => [['name' => 'ع'], 'name'],
]);

// برسالة عامة واحدة على البريد للحالين، فلا يُعرف أي الحقلين يخص حساباً، وصاحب البريد يُنبَّه بالمحاولة
// (CreateNewUser — قرار صفوان، 20 أكتوبر 2026)
it('rejects an email or phone that is already registered, with one message that names neither', function () {
    $owner = User::factory()->create(['email' => 'abdullah@example.test']);
    $this->post('/register', registrationData())
        ->assertSessionHasErrors(['email' => __('account.register.unavailable')]);
    Notification::assertSentTo($owner, RegistrationAttemptOnAccount::class);

    // التفرد على الأرقام الموثقة وحدها (VerifiedPhoneUniquenessTest)
    User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => now()]);
    $this->post('/register', registrationData(['email' => 'other@example.test', 'phone' => '051 234 5678']))
        ->assertSessionHasErrors(['email' => __('account.register.unavailable')])
        ->assertSessionDoesntHaveErrors('phone');
});

function registerNumber(int $i, array $server = []): TestResponse
{
    $response = test()->withServerVariables($server)->post('/register', registrationData([
        'email' => "user{$i}@example.test",
        'phone' => '05'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
    ]));
    auth()->logout();

    return $response;
}

it('lets a shared network register many accounts', function () {
    // شبكات الجوال والمدارس تجمع مستخدمين كثيرين خلف عنوان واحد
    foreach (range(1, 6) as $i) {
        registerNumber($i)->assertSessionHasNoErrors();
    }

    expect(User::count())->toBe(6);
});

it('still caps registrations per address at the configured limit', function () {
    config(['security.registration_per_ip_per_hour' => 2]);

    registerNumber(1)->assertSessionHasNoErrors();
    registerNumber(2)->assertSessionHasNoErrors();
    registerNumber(3)->assertSessionHasErrors('email');

    expect(User::count())->toBe(2);
});

it('counts probes of taken emails and phones toward the cap, so accounts cannot be enumerated without limit', function () {
    config(['security.registration_per_ip_per_hour' => 2]);
    User::factory()->create(['email' => 'user1@example.test']);
    User::factory()->create(['phone' => SaudiPhone::normalize('0500000002'), 'phone_verified_at' => now()]);

    registerNumber(1)->assertSessionHasErrors('email');
    registerNumber(2)->assertSessionHasErrors(['email' => __('account.register.unavailable')]);

    registerNumber(3)->assertSessionHasErrors(['email' => __('account.register.too_many')]);
    expect(User::count())->toBe(2);
});

it('counts each visitor behind a trusted load balancer separately', function () {
    config(['security.registration_per_ip_per_hour' => 1]);
    TrustProxies::at('*');

    $balancer = ['REMOTE_ADDR' => '10.0.0.1'];

    registerNumber(1, [...$balancer, 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'])->assertSessionHasNoErrors();
    registerNumber(2, [...$balancer, 'HTTP_X_FORWARDED_FOR' => '203.0.113.20'])->assertSessionHasNoErrors();
    registerNumber(3, [...$balancer, 'HTTP_X_FORWARDED_FOR' => '203.0.113.10'])->assertSessionHasErrors('email');

    TrustProxies::flushState();
});

it('counts IPv6 registrations per subscriber network, not per address', function () {
    config(['security.registration_per_ip_per_hour' => 1]);

    registerNumber(1, ['REMOTE_ADDR' => '2001:db8:abcd:1::5'])->assertSessionHasNoErrors();
    registerNumber(2, ['REMOTE_ADDR' => '2001:db8:abcd:1::9'])->assertSessionHasErrors('email');
    registerNumber(3, ['REMOTE_ADDR' => '2001:db8:abcd:2::5'])->assertSessionHasNoErrors();
});

it('pauses verification codes when the hourly SMS cap is reached', function () {
    config(['security.sms_per_hour' => 1]);

    registerNumber(1)->assertSessionHasNoErrors();
    registerNumber(2)->assertSessionHasNoErrors();

    expect($this->sms->sent)->toHaveCount(1);
});
