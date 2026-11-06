<?php

use App\Actions\Accounts\SendEmailVerificationCode;
use App\Models\Competition;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use Illuminate\Support\Facades\Notification;

/*
| رمز توثيق البريد (قرار اجتماع 6 نوفمبر 2026 — decisions/2026-11-06-email-otp.md): البريد إلزامي في التسجيل، ورمز من
| ستة أرقام إليه بعد التسجيل، ومن لم يوثّقه يدخل ويتصفح ولا يسجّل في المسابقة حتى يوثّقه.
*/

beforeEach(function () {
    Notification::fake();
});

function emailCodeSignUp(array $overrides = []): array
{
    return ['name' => 'طالب جديد', 'email' => 'student@example.test', 'phone' => '0551112222',
        'password' => 'Long-Enough-Pass-2026', 'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1', ...$overrides];
}

function lastEmailCode(User $user): string
{
    $code = '';
    Notification::assertSentTo($user, EmailVerificationCode::class, function (EmailVerificationCode $notification) use (&$code): bool {
        $code = $notification->code;

        return true;
    });

    return $code;
}

it('sends a six digit code to the email after registration and shows the code page', function () {
    $this->post('/register', emailCodeSignUp())->assertRedirect(route('verification.notice'));
    $user = User::query()->where('email', 'student@example.test')->sole();

    expect(lastEmailCode($user))->toMatch('/^\d{6}$/')
        ->and($user->hasVerifiedEmail())->toBeFalse();

    $this->get(route('verification.notice'))->assertOk()
        ->assertSee(__('account.email_code.intro', ['email' => 'student@example.test']))
        ->assertSee(route('account.email.verify.store'), false);
});

it('verifies the email by the right code, once', function () {
    $this->post('/register', emailCodeSignUp());
    $user = User::query()->where('email', 'student@example.test')->sole();
    $code = lastEmailCode($user);

    $this->post(route('account.email.verify.store'), ['code' => $code])
        ->assertSessionHasNoErrors()->assertRedirect(route('account.dashboard'));
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    // لا يصلح مرة ثانية، وبعد التوثيق يعود إلى «حسابي»
    $this->post(route('account.email.verify.store'), ['code' => $code])->assertRedirect(route('account.dashboard'));
});

it('refuses a wrong code, and voids the code after five wrong attempts', function () {
    $this->post('/register', emailCodeSignUp());
    $user = User::query()->where('email', 'student@example.test')->sole();
    $code = lastEmailCode($user);
    $wrong = $code === '000000' ? '111111' : '000000';

    foreach (range(1, SendEmailVerificationCode::MAX_ATTEMPTS) as $attempt) {
        $this->post(route('account.email.verify.store'), ['code' => $wrong])->assertSessionHasErrors(['code' => __('account.phone.invalid')]);
    }

    $this->post(route('account.email.verify.store'), ['code' => $code])->assertSessionHasErrors(['code' => __('account.phone.expired')]);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('accepts Arabic digits and refuses a malformed code', function () {
    $this->post('/register', emailCodeSignUp());
    $user = User::query()->where('email', 'student@example.test')->sole();
    $arabic = strtr(lastEmailCode($user), ['0' => '٠', '1' => '١', '2' => '٢', '3' => '٣', '4' => '٤', '5' => '٥', '6' => '٦', '7' => '٧', '8' => '٨', '9' => '٩']);

    $this->post(route('account.email.verify.store'), ['code' => '12ab'])->assertSessionHasErrors('code');
    $this->post(route('account.email.verify.store'), ['code' => $arabic])->assertSessionHasNoErrors();
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('binds the code to the email it was sent to', function () {
    $this->post('/register', emailCodeSignUp());
    $user = User::query()->where('email', 'student@example.test')->sole();
    $code = lastEmailCode($user);

    $user->forceFill(['email' => 'changed@example.test'])->save();

    $this->post(route('account.email.verify.store'), ['code' => $code])->assertSessionHasErrors(['code' => __('account.phone.expired')]);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('limits a new code to one a minute per account', function () {
    $this->post('/register', emailCodeSignUp());

    $this->post(route('account.email.send'))->assertSessionHasErrors('code');

    $this->travel(61)->seconds();
    $this->post(route('account.email.send'))->assertSessionHasNoErrors()->assertSessionHas('status', __('account.email_code.sent'));
});

it('does not verify an email that another account has verified', function () {
    $user = User::factory()->unverified()->create(['email' => 'shared@example.test', 'phone' => '+966551113333']);
    app(SendEmailVerificationCode::class)->handle($user);
    $code = lastEmailCode($user);
    User::factory()->create(['email' => 'shared@example.test']);

    $this->actingAs($user)->post(route('account.email.verify.store'), ['code' => $code])
        ->assertSessionHasErrors(['code' => __('account.email_code.taken')]);
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('keeps the code routes for signed in accounts only', function () {
    $this->post(route('account.email.send'))->assertRedirect(route('login'));
    $this->post(route('account.email.verify.store'), ['code' => '123456'])->assertRedirect(route('login'));
});

it('lets an unverified account sign in and browse, but sends it to the code before the competition registration', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);

    $this->post('/register', emailCodeSignUp());
    $user = User::query()->where('email', 'student@example.test')->sole();

    $this->get(route('account.dashboard'))->assertOk();
    $this->get('/competitions/brahin/register')->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', __('account.email_code.required'));
    $this->post('/competitions/brahin/register', [])->assertRedirect(route('verification.notice'));

    // وبعد الرمز يعود إلى نموذج التسجيل في المسابقة
    $this->post(route('account.email.verify.store'), ['code' => lastEmailCode($user)])
        ->assertRedirect(route('competitions.student.register', 'brahin'));
    $this->get('/competitions/brahin/register')->assertOk();
});

it('still lets an older account with a verified phone and no verified email register in the competition', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);
    $user = User::factory()->unverified()->create(['phone' => '+966551114444', 'phone_verified_at' => now()]);

    $this->actingAs($user)->get('/competitions/brahin/register')->assertOk();
});
