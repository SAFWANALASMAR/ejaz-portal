<?php

use App\Actions\Programs\RegisterForProgram;
use App\Actions\Users\ResetPasswordByAdmin;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use App\Support\AccountSettings;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Spatie\Activitylog\Models\Activity;
use Tests\Fakes\FakeSmsGateway;

/*
| التسجيل باسم المستخدم، والبريد والجوال اختياريان، والتوثيق مؤجل بإعداد مطفأ افتراضياً، والاستعادة للبريد أو الجوال
| الموثق وحده، وكلمة مرور مؤقتة من اللوحة لمن لا يستعيدها (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026).
*/

beforeEach(function () {
    $this->sms = FakeSmsGateway::swap();
    Notification::fake();
});

/**
 * @return array<string, mixed>
 */
function usernameRegistration(array $overrides = []): array
{
    return [
        'name' => 'عبدالله محمد',
        'username' => 'abdullah.m',
        'password' => 'Long-Enough-Pass-2026',
        'password_confirmation' => 'Long-Enough-Pass-2026',
        'privacy' => '1',
        ...$overrides,
    ];
}

it('registers with a full name, a username and a password only, without any verification message', function () {
    $this->post('/register', usernameRegistration(['username' => 'Abdullah.M']))->assertRedirect(route('account.dashboard'));

    $user = User::query()->where('username', 'abdullah.m')->firstOrFail();

    expect($user->email)->toBeNull()
        ->and($user->phone)->toBeNull()
        ->and($user->getRoleNames()->all())->toBe([RoleName::Member->value])
        ->and($this->sms->lastCodeFor('+966512345678'))->toBeNull();

    $this->assertAuthenticatedAs($user);
    Notification::assertNothingSent();
});

it('keeps the email and the phone optional, and does not send a verification link while verification is off', function () {
    $this->post('/register', usernameRegistration(['email' => 'a@example.test', 'phone' => '0512345678']))->assertRedirect(route('account.dashboard'));

    $user = User::query()->where('username', 'abdullah.m')->firstOrFail();
    expect($user->email)->toBe('a@example.test')->and($user->phone)->toBe('+966512345678');

    Notification::assertNotSentTo($user, VerifyEmailNotification::class);
    expect($this->sms->lastCodeFor('+966512345678'))->toBeNull();
});

it('sends the verification link and code again once verification is turned on from the panel', function () {
    Setting::put(AccountSettings::KEY, ['verification' => true, 'show_deferred_portals' => false]);

    $this->post('/register', usernameRegistration(['email' => 'a@example.test', 'phone' => '0512345678']))->assertRedirect(route('account.phone.verify'));

    Notification::assertSentTo(User::query()->where('username', 'abdullah.m')->firstOrFail(), VerifyEmailNotification::class);
    expect($this->sms->lastCodeFor('+966512345678'))->not->toBeNull();
});

it('rejects a malformed username, and tells plainly that a taken username is unavailable', function () {
    foreach (['ab', '1abc', 'name@x', 'عبدالله', str_repeat('a', 31)] as $bad) {
        $this->post('/register', usernameRegistration(['username' => $bad]))->assertSessionHasErrors('username');
    }

    User::factory()->create(['username' => 'taken.name']);
    $this->post('/register', usernameRegistration(['username' => 'Taken.Name']))
        ->assertSessionHasErrors(['username' => __('account.register.username_taken')]);

    expect(User::query()->where('name', 'عبدالله محمد')->exists())->toBeFalse();
});

it('refuses a password made of the username', function () {
    $this->post('/register', usernameRegistration([
        'username' => 'falcon.rider',
        'password' => 'Falcon-Rider-2026!',
        'password_confirmation' => 'Falcon-Rider-2026!',
    ]))->assertSessionHasErrors('password');
});

it('still says one general message for a used email or phone, on the field that was given', function () {
    User::factory()->create(['email' => 'used@example.test']);

    $this->post('/register', usernameRegistration(['email' => 'used@example.test']))
        ->assertSessionHasErrors(['email' => __('account.register.unavailable')]);
});

it('signs in by username, email or phone, with the same failure message for each', function () {
    $user = User::factory()->create(['username' => 'saad.k', 'email' => 'saad@example.test', 'phone' => '+966512345670', 'password' => Hash::make('Long-Enough-Pass-2026')]);

    foreach (['Saad.K', 'saad@example.test', '0512345670'] as $login) {
        $this->post('/login', ['login' => $login, 'password' => 'Long-Enough-Pass-2026']);
        $this->assertAuthenticatedAs($user);
        $this->post('/logout');
    }

    $this->post('/login', ['login' => 'saad.k', 'password' => 'wrong'])->assertSessionHasErrors('login');
    $this->assertGuest();
});

it('lets an unverified account register for programs while verification is off, and requires it once on', function () {
    $user = User::factory()->unverified()->create();
    $program = Program::factory()->create();

    expect(app(RegisterForProgram::class)->handle($user, $program, [])->exists)->toBeTrue();

    Setting::put(AccountSettings::KEY, ['verification' => true, 'show_deferred_portals' => false]);
    $other = Program::factory()->create();

    expect(fn () => app(RegisterForProgram::class)->handle($user->fresh(), $other, []))->toThrow(AuthorizationException::class);
});

it('sends the reset link only to a verified email, with the same answer either way', function () {
    $verified = User::factory()->create(['email' => 'v@example.test']);
    $unverified = User::factory()->unverified()->create(['email' => 'u@example.test']);

    $first = $this->post('/forgot-password', ['email' => 'v@example.test']);
    $second = $this->post('/forgot-password', ['email' => 'u@example.test']);

    expect($first->getSession()->get('status'))->toBe($second->getSession()->get('status'));
    Notification::assertSentTo($verified, ResetPasswordNotification::class);
    Notification::assertNotSentTo($unverified, ResetPasswordNotification::class);
});

it('gives an account without a verified email or phone a one-time password from the panel, and asks for a new one at sign-in', function (RoleName $role) {
    $admin = userWithRole($role);
    $member = User::factory()->unverified()->create(['username' => 'no.contact', 'email' => null]);
    $member->assignRole(RoleName::Member->value);

    $password = app(ResetPasswordByAdmin::class)->handle($admin, $member);

    $member->refresh();
    expect(Hash::check($password, $member->password))->toBeTrue()
        ->and($member->password_set_by_admin_at)->not->toBeNull()
        ->and(Activity::query()->where('event', ActivityEvent::PasswordSetByAdmin->value)->where('causer_id', $admin->id)->exists())->toBeTrue();

    $this->post('/login', ['login' => 'no.contact', 'password' => $password]);
    $this->get('/account')->assertRedirect(route('account.profile').'#password');

    $this->put('/user/password', ['current_password' => $password, 'password' => 'Brand-New-Pass-2026!', 'password_confirmation' => 'Brand-New-Pass-2026!']);
    expect($member->fresh()->password_set_by_admin_at)->toBeNull();
})->with([RoleName::ExecutiveDirector, RoleName::TechnicalAdmin]);

it('refuses the one-time password to the super admin, for a verified contact, and for oneself', function () {
    $verified = User::factory()->create();
    $bare = User::factory()->unverified()->create(['email' => null]);
    $admin = userWithRole(RoleName::TechnicalAdmin);

    expect(userWithRole(RoleName::SuperAdmin)->can('resetPassword', $bare))->toBeFalse()
        ->and($admin->can('resetPassword', $verified))->toBeFalse()
        ->and($admin->can('resetPassword', $admin))->toBeFalse()
        ->and(fn () => app(ResetPasswordByAdmin::class)->handle(userWithRole(RoleName::SuperAdmin), $bare))->toThrow(AuthorizationException::class);
});
