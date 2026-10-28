<?php

use App\Actions\Accounts\RequestPasswordResetCode;
use App\Actions\Accounts\ResetPasswordByPhone;
use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Actions\Fortify\CreateNewUser;
use App\Contracts\BotChallenge;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\PasswordChanged;
use App\Notifications\RegistrationCapReached;
use App\Services\BotChallenge\FakeBotChallenge;
use Illuminate\Support\Defer\DeferredCallbackCollection;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\FakeSmsGateway;

/*
| تشديد الاستعادة والتسجيل (قرار صفوان، 20 أكتوبر 2026 — docs/plans/auth-hardening.md): الاستعادة برمز الجوال، وحدود
| التسجيل لكل جهاز وشبكة وللبوابة، والتحدي ضد الآليات على نماذج الزائر.
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

function phoneAccount(array $attributes = []): User
{
    return User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => now(), 'password' => 'Old-Strong-Pass-2026', ...$attributes]);
}

function requestResetCode(string $phone = '0512345678')
{
    return test()->post(route('password.phone.send'), ['phone' => $phone]);
}

function resetByPhone(string $code, string $password = 'Brand-New-Phone-2026')
{
    return test()->post(route('password.phone.update'), ['code' => $code, 'password' => $password, 'password_confirmation' => $password]);
}

// ── الاستعادة برمز الجوال

it('offers the phone option and its routes only when SMS is ready, outside development', function () {
    $this->get(route('password.request'))->assertOk()->assertSee(route('password.phone.send'), false);

    app()->detectEnvironment(fn () => 'production');
    // حماية النماذج تعمل في الإنتاج، والمقصود هنا الإخفاء
    $this->withoutMiddleware();
    $this->get(route('password.request'))->assertOk()->assertDontSee(route('password.phone.send'), false);
    requestResetCode()->assertNotFound();
    $this->get(route('password.phone.reset'))->assertNotFound();
});

it('keeps the phone option off on a staging server without SMS, as in production', function () {
    app()->detectEnvironment(fn () => 'staging');
    expect(ResetPasswordByPhone::available())->toBeFalse();

    app()->detectEnvironment(fn () => 'local');
    expect(ResetPasswordByPhone::available())->toBeTrue();
});

it('refuses the fake bot challenge in production, from the container and from the fake itself', function () {
    app()->detectEnvironment(fn () => 'production');
    config(['services.bot_challenge.driver' => 'fake']);
    app()->forgetInstance(BotChallenge::class);

    expect(fn () => app(BotChallenge::class))->toThrow(RuntimeException::class)
        ->and(fn () => (new FakeBotChallenge)->passes('token', 'login'))->toThrow(RuntimeException::class);
});

it('answers the same whether the number belongs to a verified account or not, and sends a code to that account only', function (?array $account, bool $sends) {
    if ($account !== null) {
        phoneAccount($account);
    }

    requestResetCode()->assertRedirect(route('password.phone.reset'))->assertSessionHas('status', __('account.password_reset.sent'));

    expect($this->sms->lastCodeFor('+966512345678') !== null)->toBe($sends);
})->with([
    'verified account' => [[], true],
    'no account' => [null, false],
    'number not verified' => [['phone_verified_at' => null], false],
    'deactivated account' => [['is_active' => false], false],
]);

it('sends the reset code after the response, so the request takes the same work for a registered number and an unknown one', function () {
    phoneAccount();
    $deferred = app(DeferredCallbackCollection::class);

    app(RequestPasswordResetCode::class)->handle('0599999999');
    expect($deferred->count())->toBe(0);

    app(RequestPasswordResetCode::class)->handle('0512345678');
    expect($deferred->count())->toBe(1)
        ->and($this->sms->sent)->toBe([]);

    $deferred->invoke();
    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
});

it('refuses a malformed number, which reveals nothing about the accounts', function () {
    requestResetCode('12345')->assertSessionHasErrors('phone');
});

it('resets with the right code once, notifies, renews remember me and does not sign in', function () {
    $user = phoneAccount(['remember_token' => 'old-remember-token']);
    requestResetCode();
    $code = $this->sms->lastCodeFor('+966512345678');

    resetByPhone($code)->assertRedirect(route('login'))->assertSessionHas('status', __('account.password_reset.done'));

    $user->refresh();
    expect(Hash::check('Brand-New-Phone-2026', $user->password))->toBeTrue()
        ->and($user->remember_token)->not->toBe('old-remember-token')
        ->and(Activity::query()->where('event', ActivityEvent::PasswordReset->value)->whereMorphedTo('subject', $user)->exists())->toBeTrue();
    Notification::assertSentTo($user, PasswordChanged::class);
    $this->assertGuest();

    // لمرة واحدة: الجلسة فقدت الرقم، والرمز نفسه برقم الجلسة من جديد لا يصلح
    $this->withSession(['password_reset.phone' => '+966512345678']);
    resetByPhone($code, 'Another-Phone-Pass-2026')->assertSessionHasErrors(['code' => __('account.password_reset.expired')]);
});

it('voids the code after five wrong attempts, and keeps it when only the password is refused', function () {
    phoneAccount();
    requestResetCode();
    $code = $this->sms->lastCodeFor('+966512345678');
    $wrong = $code === '111111' ? '222222' : '111111';

    // كلمة مرور ضعيفة لا تستهلك الرمز
    resetByPhone($code, 'weak')->assertSessionHasErrors('password');

    foreach (range(1, 5) as $attempt) {
        resetByPhone($wrong)->assertSessionHasErrors(['code' => __('account.password_reset.invalid')]);
    }
    resetByPhone($code)->assertSessionHasErrors(['code' => __('account.password_reset.expired')]);
});

it('gives a number without an account the same messages, invalid five times then expired', function () {
    $this->withSession(['password_reset.phone' => '+966599999999']);

    foreach (range(1, 5) as $attempt) {
        resetByPhone('123456')->assertSessionHasErrors(['code' => __('account.password_reset.invalid')]);
    }
    resetByPhone('123456')->assertSessionHasErrors(['code' => __('account.password_reset.expired')]);
});

it('answers a weak or personal password with a wrong code the same for a registered number and an unknown one', function (string $password) {
    phoneAccount(['name' => 'صاحب الحساب', 'email' => 'owner.secret@example.test']);
    requestResetCode();
    $wrong = $this->sms->lastCodeFor('+966512345678') === '111111' ? '222222' : '111111';

    // الرقم المسجل: الرمز يُفحص قبل كلمة المرور، فلا خطأ لكلمة المرور ولا تلميح إلى الاسم أو البريد
    $registered = resetByPhone($wrong, $password)->assertSessionHasErrors(['code' => __('account.password_reset.invalid')])
        ->assertSessionDoesntHaveErrors('password');
    $registeredErrors = session('errors')->getBag('default')->toArray();

    $this->flushSession();
    $this->withSession(['password_reset.phone' => '+966599999999']);
    resetByPhone($wrong, $password)->assertSessionDoesntHaveErrors('password');

    expect(session('errors')->getBag('default')->toArray())->toBe($registeredErrors);
})->with([
    'weak' => ['weak'],
    'holds the email' => ['owner.secret@example.test-2026-X!'],
]);

it('applies the SMS limits to reset codes, without telling the requester', function () {
    phoneAccount();

    requestResetCode()->assertSessionHas('status', __('account.password_reset.sent'));
    requestResetCode()->assertSessionHas('status', __('account.password_reset.sent'));

    expect($this->sms->sent)->toHaveCount(1);
});

it('keeps the verification code available when someone drains the reset codes of a number', function () {
    $user = phoneAccount();
    $send = app(SendPhoneVerificationCode::class);

    // خمسة رموز استعادة في الساعة تملأ حد الرقم للاستعادة
    foreach (range(1, 5) as $i) {
        RateLimiter::clear('otp-minute:reset:'.$user->id);
        RateLimiter::clear('otp-hour:reset:'.$user->id);
        $send->handle($user, SendPhoneVerificationCode::RESET);
    }
    RateLimiter::clear('otp-minute:reset:'.$user->id);
    RateLimiter::clear('otp-hour:reset:'.$user->id);

    expect(fn () => $send->handle($user, SendPhoneVerificationCode::RESET))->toThrow(ValidationException::class);

    // والتوثيق بحدوده
    $send->handle($user, SendPhoneVerificationCode::VERIFY);
    expect($this->sms->sent)->toHaveCount(6);
});

it('keeps two-factor on a panel account after a phone reset', function () {
    $user = phoneAccount();
    $user->forceFill(['two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'), 'two_factor_confirmed_at' => now()])->save();
    $user->assignRole(RoleName::Secretary->value);
    requestResetCode();
    resetByPhone($this->sms->lastCodeFor('+966512345678'));

    $this->post('/login', ['login' => $user->email, 'password' => 'Brand-New-Phone-2026'])->assertRedirect('/two-factor-challenge');
    $this->assertGuest();
});

// ── حدود التسجيل

function registerAt(int $i, array $server = [], array $cookies = [])
{
    $response = test()->withServerVariables($server)->withCookies($cookies)->post('/register', [
        'name' => 'مستفيد تجريبي',
        'email' => "limit{$i}@example.test",
        'phone' => '05'.str_pad((string) $i, 8, '0', STR_PAD_LEFT),
        'password' => 'Limits-Strong-Pass-2026',
        'password_confirmation' => 'Limits-Strong-Pass-2026',
        'username' => uniqueUsername(), 'privacy' => '1',
    ]);
    auth()->logout();

    return $response;
}

it('caps sign ups per device by its own cookie, not by the browser string many share', function () {
    config(['security.registration_per_device_per_hour' => 2]);
    // جهاز بلا كعكة يأخذ كعكة جديدة
    expect(registerAt(5)->getCookie(CreateNewUser::DEVICE_COOKIE, false))->not->toBeNull();

    $device = ['ejaz_device' => str_repeat('a1', 16)];

    registerAt(1, [], $device)->assertSessionHasNoErrors();
    registerAt(2, [], $device)->assertSessionHasNoErrors();
    registerAt(3, [], $device)->assertSessionHasErrors(['username' => __('account.register.too_many')]);

    // جهاز آخر خلف العنوان نفسه وبنص المتصفح نفسه يسجّل
    registerAt(4, [], ['ejaz_device' => str_repeat('b2', 16)])->assertSessionHasNoErrors();
});

it('caps sign ups per network a day, so rotating addresses in one /24 does not help', function () {
    config(['security.registration_per_network_per_day' => 2]);

    registerAt(1, ['REMOTE_ADDR' => '198.51.100.10'])->assertSessionHasNoErrors();
    registerAt(2, ['REMOTE_ADDR' => '198.51.100.20'])->assertSessionHasNoErrors();
    registerAt(3, ['REMOTE_ADDR' => '198.51.100.30'])->assertSessionHasErrors(['username' => __('account.register.too_many')]);
    registerAt(4, ['REMOTE_ADDR' => '203.0.113.30'])->assertSessionHasNoErrors();
});

it('pauses sign up at the portal cap and tells the super admins once', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    config(['security.registration_per_hour' => 1]);

    registerAt(1)->assertSessionHasNoErrors();
    registerAt(2)->assertSessionHasErrors(['username' => __('account.register.busy')]);
    registerAt(3)->assertSessionHasErrors(['username' => __('account.register.busy')]);

    Notification::assertSentToTimes($admin, RegistrationCapReached::class, 1);
});

it('keeps probes of taken emails out of the portal cap, so they cannot pause sign up for everyone', function () {
    config(['security.registration_per_hour' => 2]);
    foreach (range(1, 3) as $i) {
        User::factory()->create(['email' => "taken{$i}@example.test"]);
    }

    // ثلاث محاولات ببُرُد مستخدمة من أجهزة وعناوين مختلفة، فوق سقف البوابة
    foreach (range(1, 3) as $i) {
        test()->withServerVariables(['REMOTE_ADDR' => "198.51.10{$i}.7"])->withCookies(['ejaz_device' => str_repeat("c{$i}", 16)])
            ->post('/register', ['name' => 'مستفيد تجريبي', 'email' => "taken{$i}@example.test", 'phone' => "05600000{$i}0",
                'password' => 'Limits-Strong-Pass-2026', 'password_confirmation' => 'Limits-Strong-Pass-2026', 'username' => uniqueUsername(), 'privacy' => '1'])
            ->assertSessionHasErrors(['email' => __('account.register.unavailable')]);
    }

    registerAt(1, ['REMOTE_ADDR' => '203.0.113.40'])->assertSessionHasNoErrors();
});

// الجوال اختياري منذ ملاحظات المدير التنفيذي (25 أكتوبر 2026)، واسم المستخدم إلزامي: خطأ تحقق لا خطأ خادم
it('accepts a sign up without a phone field, and refuses one without a username as a validation error, not a server error', function () {
    $this->post('/register', ['name' => 'مستفيد تجريبي', 'email' => 'nophone@example.test',
        'password' => 'Limits-Strong-Pass-2026', 'password_confirmation' => 'Limits-Strong-Pass-2026', 'username' => uniqueUsername(), 'privacy' => '1'])
        ->assertRedirect()->assertSessionHasNoErrors();

    auth()->logout();

    $this->post('/register', ['name' => 'مستفيد آخر', 'email' => 'nouser@example.test',
        'password' => 'Limits-Strong-Pass-2026', 'password_confirmation' => 'Limits-Strong-Pass-2026', 'privacy' => '1'])
        ->assertRedirect()->assertSessionHasErrors('username');
});

// ── التحدي ضد الآليات على نماذج الزائر

it('refuses the guest forms when the challenge fails, before anything else runs', function () {
    app()->instance(BotChallenge::class, new FakeBotChallenge(passes: false));
    $user = phoneAccount(['email' => 'challenge@example.test']);

    $this->from('/login')->post('/login', ['login' => 'challenge@example.test', 'password' => 'Old-Strong-Pass-2026'])
        ->assertRedirect('/login')->assertSessionHasErrors(['bot' => __('account.bot_challenge.failed')]);
    $this->assertGuest();

    registerAt(1)->assertSessionHasErrors('bot');
    expect(User::query()->where('email', 'limit1@example.test')->exists())->toBeFalse();

    $this->post(route('password.email'), ['email' => 'challenge@example.test'])->assertSessionHasErrors('bot');
    requestResetCode()->assertSessionHasErrors('bot');
    expect($this->sms->sent)->toBe([]);

    // وصاحب الحساب المسجل لا يمر بالتحدي في غيرها
    $this->actingAs($user)->get('/account')->assertOk();
});

it('shows the challenge and opens the CSP to it on the guest forms only, when Turnstile is the driver', function () {
    config(['services.bot_challenge.driver' => 'turnstile']);
    app()->instance(BotChallenge::class, new FakeBotChallenge(siteKey: 'site-key-test'));

    foreach (['/login', '/register', '/forgot-password'] as $page) {
        $response = $this->get($page)->assertOk()->assertSee('data-sitekey="site-key-test"', false);
        expect((string) $response->headers->get('Content-Security-Policy'))->toContain('https://challenges.cloudflare.com');
    }

    expect((string) $this->get('/contact')->headers->get('Content-Security-Policy'))->not->toContain('challenges.cloudflare.com');
});
