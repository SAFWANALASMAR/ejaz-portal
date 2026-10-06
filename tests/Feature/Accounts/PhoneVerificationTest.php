<?php

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SmsCapWarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeSmsGateway;

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    $this->user = User::factory()->create(['phone' => '+966512345678']);
});

/**
 * عنوان الزائر للاستدعاء المباشر للإجراء خارج طلب HTTP.
 */
function fromAddress(string $ip): void
{
    request()->server->set('REMOTE_ADDR', $ip);
}

function sendCode(User $user): string
{
    app(SendPhoneVerificationCode::class)->handle($user);

    return (string) test()->sms->lastCodeFor((string) $user->phone);
}

it('verifies the phone with the code that was sent', function () {
    $code = sendCode($this->user);

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertRedirect(route('account.dashboard'));

    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('accepts the code typed in Arabic-Indic digits', function () {
    $code = sendCode($this->user);
    $arabic = strtr($code, ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $arabic])->assertSessionHasNoErrors();

    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('rejects a wrong code and invalidates it after five attempts', function () {
    $code = sendCode($this->user);
    $wrong = $code === '111111' ? '222222' : '111111';

    foreach (range(1, 5) as $ignored) {
        $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $wrong])
            ->assertSessionHasErrors(['code' => __('account.phone.invalid')]);
    }

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertSessionHasErrors(['code' => __('account.phone.expired')]);

    expect($this->user->fresh()->hasVerifiedPhone())->toBeFalse();
});

it('does not accept a code after the phone number changes', function () {
    $code = sendCode($this->user);
    $this->user->forceFill(['phone' => '+966598765432'])->save();

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertSessionHasErrors('code');
});

it('limits how often a new code can be requested', function () {
    $this->actingAs($this->user)->post('/account/verify-phone/send')->assertSessionHasNoErrors();
    $this->actingAs($this->user)->post('/account/verify-phone/send')->assertSessionHasErrors('code');

    expect($this->sms->sent)->toHaveCount(1);
});

it('verifies one request at a time for the same user', function () {
    config(['security.otp_lock_wait_seconds' => 0]);
    $code = sendCode($this->user);

    // طلب آخر يحمل القفل الآن
    $lock = Cache::lock('phone-otp-verify:'.$this->user->id, 10);
    $lock->get();

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])
        ->assertSessionHasErrors(['code' => __('account.phone.busy')]);

    $lock->release();

    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();
    expect($this->user->fresh()->hasVerifiedPhone())->toBeTrue();
});

it('warns the super admin at 90 percent of the hourly cap without stopping codes', function () {
    Notification::fake();
    config(['security.sms_per_hour' => 10]);
    $admin = userWithRole(RoleName::SuperAdmin);

    foreach (range(1, 8) as $ignored) {
        RateLimiter::hit('sms:global', 3600);
    }

    sendCode($this->user);

    // الرسالة التاسعة من عشر: أُرسلت، وصدر تنبيها 70% و90%
    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
    Notification::assertSentTo($admin, SmsCapWarning::class, fn (SmsCapWarning $warning) => $warning->percent === 90);
    Notification::assertSentTo($admin, SmsCapWarning::class, fn (SmsCapWarning $warning) => $warning->percent === 70);
});

it('sends each threshold warning once an hour', function () {
    Notification::fake();
    config(['security.sms_per_hour' => 4]);
    $admin = userWithRole(RoleName::SuperAdmin);
    RateLimiter::hit('sms:global', 3600);
    RateLimiter::hit('sms:global', 3600);

    sendCode($this->user);
    sendCode(User::factory()->create(['phone' => '+966511111111']));

    Notification::assertSentToTimes($admin, SmsCapWarning::class, 2);
});

it('limits unverified codes from one network without blocking other networks', function () {
    config(['security.sms_unverified_per_network' => 2]);

    foreach (['+966511111111', '+966522222222'] as $phone) {
        fromAddress('203.0.113.5');
        sendCode(User::factory()->create(['phone' => $phone]));
    }

    // عنوان آخر في الشبكة نفسها (/24)
    fromAddress('203.0.113.77');
    expect(fn () => sendCode($this->user))->toThrow(ValidationException::class);

    fromAddress('198.51.100.5');
    sendCode($this->user);
    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
});

it('frees the network slot once a code is verified', function () {
    config(['security.sms_unverified_per_network' => 1]);
    fromAddress('203.0.113.5');

    $code = sendCode($this->user);
    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();

    $other = User::factory()->create(['phone' => '+966533333333']);
    sendCode($other);
    expect($this->sms->lastCodeFor('+966533333333'))->not->toBeNull();
});

it('keeps the verification page for signed-in users only', function () {
    $this->get('/account/verify-phone')->assertRedirect(route('login'));
});
