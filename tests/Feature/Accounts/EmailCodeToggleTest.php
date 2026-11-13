<?php

use App\Actions\Competitions\StartSubmission;
use App\Actions\Settings\UpdateSiteSettings;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Support\AccountSettings;
use Illuminate\Support\Facades\Notification;

/*
| مفتاح «رمز توثيق البريد بعد التسجيل» في إعدادات الحسابات (صفوان، 13 نوفمبر 2026 —
| decisions/2026-11-13-feature-email-code-toggle.md): مفعّل افتراضياً كما كان، ويُطفأ مؤقتاً ما دام بريد الموقع لا يرسل.
| وإطفاؤه لا يُسقط إلزام الجوال والبريد في التسجيل.
*/

beforeEach(function () {
    Notification::fake();
});

function toggleSignUp(array $overrides = []): array
{
    return ['name' => 'طالب جديد', 'email' => 'student@example.test', 'phone' => '0551112222',
        'password' => 'Long-Enough-Pass-2026', 'password_confirmation' => 'Long-Enough-Pass-2026', 'privacy' => '1', ...$overrides];
}

function turnEmailCodeOff(): void
{
    Setting::put(AccountSettings::KEY, ['verification' => false, 'email_code' => false, 'show_deferred_portals' => false]);
}

it('keeps the email code on by default', function () {
    expect(AccountSettings::emailCodeEnabled())->toBeTrue();

    $this->post('/register', toggleSignUp())->assertRedirect(route('verification.notice'));
    Notification::assertSentTo(User::query()->where('email', 'student@example.test')->sole(), EmailVerificationCode::class);
});

it('sends no code and goes straight to the account when the email code is off', function () {
    turnEmailCodeOff();

    $this->post('/register', toggleSignUp())->assertRedirect(route('account.dashboard'));
    $user = User::query()->where('email', 'student@example.test')->sole();

    Notification::assertNothingSentTo($user);
    $this->get(route('account.dashboard'))->assertOk()->assertDontSee(__('account.dashboard.verify_email'));
});

it('still shows the unverified email alert while the code is on', function () {
    $this->actingAs(User::factory()->unverified()->create(['phone' => '+966551113333']))
        ->get(route('account.dashboard'))->assertOk()->assertSee(__('account.dashboard.verify_email'));
});

it('still requires the phone and the email at registration when the code is off', function () {
    turnEmailCodeOff();

    $this->post('/register', toggleSignUp(['email' => '']))->assertSessionHasErrors('email');
    $this->post('/register', toggleSignUp(['phone' => '']))->assertSessionHasErrors('phone');
    expect(User::query()->count())->toBe(0);
});

it('lets an unverified account register in the student competition when the code is off', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);
    Competition::factory()->student()->create(['slug' => 'brahin', 'is_published' => true]);
    $user = User::factory()->unverified()->create(['phone' => '+966551114444', 'phone_verified_at' => null]);

    $this->actingAs($user)->get('/competitions/brahin/register')->assertRedirect(route('verification.notice'));

    turnEmailCodeOff();
    $this->get('/competitions/brahin/register')->assertOk();
    Notification::assertNotSentTo($user, EmailVerificationCode::class);
});

it('lets an unverified account start a general competition entry when the code is off', function () {
    $competition = Competition::factory()->withQuestions()->create(['slug' => 'demo']);
    $user = User::factory()->unverified()->create(['phone' => '+966551115555', 'phone_verified_at' => null]);

    $this->actingAs($user)->post('/competitions/demo/entry/start')->assertRedirect(route('verification.notice'));
    expect(CompetitionSubmission::count())->toBe(0);

    turnEmailCodeOff();
    $this->post('/competitions/demo/entry/start')->assertRedirect('/competitions/demo/entry');
    expect(app(StartSubmission::class)->handle($user, $competition)->user_id)->toBe($user->id);
});

it('saves the toggle from the settings action, and keeps it when a save omits it', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);

    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => false, 'email_code' => false, 'show_deferred_portals' => false]]);
    expect(AccountSettings::emailCodeEnabled())->toBeFalse();

    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => false, 'show_deferred_portals' => true]]);
    expect(AccountSettings::emailCodeEnabled())->toBeFalse()
        ->and(AccountSettings::showsDeferredPortals())->toBeTrue();

    app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => false, 'email_code' => true, 'show_deferred_portals' => true]]);
    expect(AccountSettings::emailCodeEnabled())->toBeTrue();
});

it('refuses the toggle from an account without accounts.update, and a non boolean value', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(UpdateSiteSettings::class)->handle($editor, [AccountSettings::KEY => ['verification' => false, 'email_code' => false, 'show_deferred_portals' => false]]))
        ->toThrow(Illuminate\Auth\Access\AuthorizationException::class);

    $admin = userWithRole(RoleName::TechnicalAdmin);
    expect(fn () => app(UpdateSiteSettings::class)->handle($admin, [AccountSettings::KEY => ['verification' => false, 'email_code' => 'maybe', 'show_deferred_portals' => false]]))
        ->toThrow(Illuminate\Validation\ValidationException::class);
    expect(AccountSettings::emailCodeEnabled())->toBeTrue();
});
