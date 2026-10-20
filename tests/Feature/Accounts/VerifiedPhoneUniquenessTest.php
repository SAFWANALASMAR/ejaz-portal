<?php

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Fakes\FakeSmsGateway;

/*
| حجز رقم الجوال من حساب غير موثق (المجموعة ٣، البند ٢ — decisions.md 14 أكتوبر 2026):
| التفرد على الأرقام الموثقة وحدها، والتوثيق يحرّر الرقم من الحسابات غير الموثقة، والحساب
| غير الموثق يُحذف بعد 14 يوماً إن لم يرتبط بسجل ولا بدور غير المستفيد.
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

function squatter(array $attributes = []): User
{
    return User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => null, ...$attributes]);
}

function registerWithPhone(string $email): TestResponse
{
    return test()->post('/register', [
        'name' => 'عبدالله محمد',
        'email' => $email,
        'phone' => '0512345678',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
    ]);
}

function verifyPhoneOf(User $user): TestResponse
{
    request()->server->set('REMOTE_ADDR', '203.0.113.7');
    app(SendPhoneVerificationCode::class)->handle($user);
    $code = (string) test()->sms->lastCodeFor((string) $user->phone);

    return test()->actingAs($user)->post('/account/verify-phone', ['code' => $code]);
}

it('lets the owner register a number that an unverified account holds', function () {
    squatter();

    registerWithPhone('owner@example.test')->assertSessionHasNoErrors();

    expect(User::where('email', 'owner@example.test')->value('phone'))->toBe('+966512345678');
});

it('still rejects a number that a verified account holds', function () {
    squatter(['phone_verified_at' => now()]);

    registerWithPhone('owner@example.test')->assertSessionHasErrors('phone');

    expect(User::where('email', 'owner@example.test')->exists())->toBeFalse();
});

it('lets a profile take a number held only by an unverified account, not by a verified one', function () {
    $user = User::factory()->create(['phone' => '+966500000009', 'phone_verified_at' => now()]);
    $data = fn (string $phone) => ['name' => $user->name, 'email' => $user->email, 'phone' => $phone, 'current_password' => 'password'];

    squatter();
    $this->actingAs($user)->put('/user/profile-information', $data('0512345678'))->assertSessionHasNoErrors();
    expect($user->fresh()->phone)->toBe('+966512345678');

    User::factory()->create(['phone' => '+966511111111', 'phone_verified_at' => now()]);
    $this->actingAs($user)->put('/user/profile-information', $data('0511111111'))
        ->assertSessionHasErrors('phone', null, 'updateProfileInformation');
});

it('frees the number from unverified accounts once its owner verifies it', function () {
    $squatter = squatter();
    $owner = squatter();

    verifyPhoneOf($owner)->assertSessionHasNoErrors();

    expect($owner->fresh()->hasVerifiedPhone())->toBeTrue()
        ->and($squatter->fresh()->phone)->toBeNull();
});

it('refuses to verify a number already verified on another account', function () {
    $holder = squatter(['phone_verified_at' => now()]);
    $late = squatter();

    verifyPhoneOf($late)->assertSessionHasErrors(['code' => __('account.phone.taken')]);

    expect($late->fresh()->hasVerifiedPhone())->toBeFalse()
        ->and($holder->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('logs in by phone to the verified account, not to an unverified one holding the same number', function () {
    squatter();
    $owner = squatter(['email' => 'owner@example.test', 'phone_verified_at' => now()]);

    // الحساب غير الموثق أقدم رقماً، فيأتي أولاً لولا تفضيل الموثق
    $this->post('/login', ['login' => '0512345678', 'password' => 'password'])->assertRedirect('/account');

    $this->assertAuthenticatedAs($owner);
});

it('schedules the unverified accounts cleanup daily', function () {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'ejaz:prune-unverified-accounts'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 0 * * *');
});
