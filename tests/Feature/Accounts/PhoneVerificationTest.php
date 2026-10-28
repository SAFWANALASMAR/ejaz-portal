<?php

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\SmsCapWarning;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Tests\Fakes\FakeSmsGateway;

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    $this->user = User::factory()->create(['phone' => '+966512345678']);
});

/**
 * عنوان الزائر للاستدعاء المباشر للإجراء خارج طلب HTTP. كل طلب HTTP في الاختبار
 * يستبدل كائن الطلب، فيُعاد ضبط العنوان بعده.
 */
function verificationRequestFrom(string $ip): void
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
    $admin = userWithRole(RoleName::TechnicalAdmin);

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
    $admin = userWithRole(RoleName::TechnicalAdmin);
    RateLimiter::hit('sms:global', 3600);
    RateLimiter::hit('sms:global', 3600);

    sendCode($this->user);
    sendCode(User::factory()->create(['phone' => '+966511111111']));

    Notification::assertSentToTimes($admin, SmsCapWarning::class, 2);
});

it('limits unverified codes from one network without blocking other networks', function () {
    config(['security.sms_pending_per_ipv4_network' => 2]);

    foreach (['+966511111111', '+966522222222'] as $phone) {
        verificationRequestFrom('203.0.113.5');
        sendCode(User::factory()->create(['phone' => $phone]));
    }

    // عنوان آخر في الشبكة نفسها (/24)
    verificationRequestFrom('203.0.113.77');
    expect(fn () => sendCode($this->user))->toThrow(ValidationException::class);

    verificationRequestFrom('198.51.100.5');
    sendCode($this->user);
    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
});

it('frees the network slot once a code is verified', function () {
    config(['security.sms_pending_per_ipv4_network' => 1]);
    verificationRequestFrom('203.0.113.5');

    $code = sendCode($this->user);
    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();

    verificationRequestFrom('203.0.113.9');
    $other = User::factory()->create(['phone' => '+966533333333']);
    sendCode($other);
    expect($this->sms->lastCodeFor('+966533333333'))->not->toBeNull();
});

it('counts a resent code once', function () {
    config(['security.sms_pending_per_ipv4_network' => 1]);
    verificationRequestFrom('203.0.113.5');

    sendCode($this->user);
    $this->travel(61)->seconds();
    sendCode($this->user);

    verificationRequestFrom('203.0.113.9');
    expect(fn () => sendCode(User::factory()->create(['phone' => '+966511111111'])))->toThrow(ValidationException::class);
});

it('frees the slot after a resend and a successful verification', function () {
    config(['security.sms_pending_per_ipv4_network' => 1]);
    verificationRequestFrom('203.0.113.5');

    sendCode($this->user);
    $this->travel(61)->seconds();
    $code = sendCode($this->user);
    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();

    verificationRequestFrom('203.0.113.9');
    sendCode(User::factory()->create(['phone' => '+966511111111']));
    expect($this->sms->lastCodeFor('+966511111111'))->not->toBeNull();
});

it('limits pending codes per IPv6 subscriber and per site', function () {
    config(['security.sms_pending_per_ipv6_subscriber' => 1, 'security.sms_pending_per_ipv6_site' => 2]);
    $phones = ['+966511111111', '+966522222222', '+966533333333', '+966544444444'];
    $users = array_map(fn (string $phone) => User::factory()->create(['phone' => $phone]), $phones);

    verificationRequestFrom('2001:db8:abcd:1::5');
    sendCode($users[0]);

    // العنوان نفسه في الشبكة /64: حد المشترك
    verificationRequestFrom('2001:db8:abcd:1::9');
    expect(fn () => sendCode($users[1]))->toThrow(ValidationException::class);

    // مشترك آخر في الموقع /48 نفسه: يُقبل حتى حد الموقع
    verificationRequestFrom('2001:db8:abcd:2::5');
    sendCode($users[2]);

    verificationRequestFrom('2001:db8:abcd:3::5');
    expect(fn () => sendCode($users[3]))->toThrow(ValidationException::class);
});

it('counts an IPv4 address written in IPv6 form in its IPv4 network', function () {
    config(['security.sms_pending_per_ipv4_network' => 1]);

    verificationRequestFrom('::ffff:203.0.113.5');
    sendCode($this->user);

    verificationRequestFrom('203.0.113.9');
    expect(fn () => sendCode(User::factory()->create(['phone' => '+966511111111'])))->toThrow(ValidationException::class);
});

it('does not apply the network limit to private addresses', function () {
    config(['security.sms_pending_per_ipv4_network' => 0, 'security.sms_sent_per_ipv4_network' => 0]);
    verificationRequestFrom('10.0.0.5');

    sendCode($this->user);

    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
});

it('warns once an hour that the network limit is off for private addresses', function () {
    Log::spy();
    verificationRequestFrom('10.0.0.5');

    sendCode($this->user);
    $this->travel(61)->seconds();
    sendCode($this->user);

    Log::shouldHaveReceived('warning')
        ->withArgs(fn (string $message) => str_contains($message, 'TRUSTED_PROXIES'))
        ->once();
});

it('counts every sent code against the network, resends included', function () {
    config(['security.sms_pending_per_ipv4_network' => 10, 'security.sms_sent_per_ipv4_network' => 2]);
    verificationRequestFrom('203.0.113.5');

    sendCode($this->user);
    $this->travel(61)->seconds();
    sendCode($this->user);
    $this->travel(61)->seconds();

    // رمز معلّق واحد فقط، لكن الشبكة أرسلت رسالتين
    expect(fn () => sendCode($this->user))->toThrow(ValidationException::class);

    verificationRequestFrom('203.0.113.9');
    expect(fn () => sendCode(User::factory()->create(['phone' => '+966511111111'])))->toThrow(ValidationException::class)
        ->and($this->sms->sent)->toHaveCount(2);
});

it('counts verified codes against the network send limit too', function () {
    config(['security.sms_sent_per_ipv4_network' => 1]);
    verificationRequestFrom('203.0.113.5');

    $code = sendCode($this->user);
    $this->actingAs($this->user)->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();

    verificationRequestFrom('203.0.113.9');
    expect(fn () => sendCode(User::factory()->create(['phone' => '+966511111111'])))->toThrow(ValidationException::class);
});

it('limits sent codes per IPv6 subscriber and per site', function () {
    config(['security.sms_sent_per_ipv6_subscriber' => 1, 'security.sms_sent_per_ipv6_site' => 2]);
    $phones = ['+966511111111', '+966522222222', '+966533333333', '+966544444444'];
    $users = array_map(fn (string $phone) => User::factory()->create(['phone' => $phone]), $phones);

    verificationRequestFrom('2001:db8:abcd:1::5');
    $code = sendCode($users[0]);
    $this->actingAs($users[0])->post('/account/verify-phone', ['code' => $code])->assertSessionHasNoErrors();

    // الرمز الأول تحقق، ومع ذلك استنفد المشترك حده من الرسائل
    verificationRequestFrom('2001:db8:abcd:1::9');
    expect(fn () => sendCode($users[1]))->toThrow(ValidationException::class);

    verificationRequestFrom('2001:db8:abcd:2::5');
    sendCode($users[2]);

    verificationRequestFrom('2001:db8:abcd:3::5');
    expect(fn () => sendCode($users[3]))->toThrow(ValidationException::class);
});

it('opens the network again once the hour passes', function () {
    config(['security.sms_pending_per_ipv4_network' => 1, 'security.sms_sent_per_ipv4_network' => 1]);
    verificationRequestFrom('203.0.113.5');
    sendCode($this->user);

    $other = User::factory()->create(['phone' => '+966511111111']);
    verificationRequestFrom('203.0.113.9');
    expect(fn () => sendCode($other))->toThrow(ValidationException::class);

    $this->travel(3601)->seconds();
    sendCode($other);

    expect($this->sms->lastCodeFor('+966511111111'))->not->toBeNull();
});

it('limits the codes sent to one number, whichever account asks for them', function () {
    $victim = '+966500000001';

    // المهاجم يسجّل برقم الضحية، ثم ينقل الرقم إلى حساب جديد بعد استنفاد حد الحساب
    foreach (range(1, 5) as $i) {
        $account = User::factory()->create(['phone' => $victim]);
        sendCode($account);
        $account->forceFill(['phone' => '+96651111111'.$i])->save();
    }

    $next = User::factory()->create(['phone' => $victim]);

    expect(fn () => sendCode($next))->toThrow(ValidationException::class, __('account.phone.number_busy'))
        ->and(collect($this->sms->sent)->where('phone', $victim))->toHaveCount(5);
});

it('caps the codes to one number at ten a day', function () {
    $victim = '+966500000002';
    $account = User::factory()->create(['phone' => $victim]);

    foreach (range(1, 2) as $hour) {
        foreach (range(1, 5) as $ignored) {
            RateLimiter::clear('otp-minute:'.$account->id);
            RateLimiter::clear('otp-hour:'.$account->id);
            sendCode($account);
        }

        $this->travel(3601)->seconds();
    }

    RateLimiter::clear('otp-hour:'.$account->id);

    expect(fn () => sendCode($account))->toThrow(ValidationException::class, __('account.phone.number_busy'));
});

it('keeps the send limit of every network at a tenth of the global cap or less', function () {
    $cap = (int) config('security.sms_per_hour');

    foreach (['ipv4_network', 'ipv6_subscriber', 'ipv6_site'] as $scope) {
        $sent = (int) config("security.sms_sent_per_{$scope}");

        expect($sent)->toBeGreaterThan(0)->toBeLessThanOrEqual(intdiv($cap, 10))
            ->and((int) config("security.sms_pending_per_{$scope}"))->toBeLessThanOrEqual($sent);
    }
});

it('keeps the verification page for signed-in users only', function () {
    $this->get('/account/verify-phone')->assertRedirect(route('login'));
});
