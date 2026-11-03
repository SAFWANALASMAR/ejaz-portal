<?php

use App\Actions\Fortify\EnableTwoFactorAuthentication;
use App\Actions\Users\ResetTwoFactorByAdmin;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Activities\ActivityResource;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Notifications\TwoFactorResetByAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PragmaRX\Google2FA\Google2FA;
use Spatie\Activitylog\Models\Activity;
use Symfony\Component\HttpKernel\Exception\HttpException;

/*
| إعادة ضبط التحقق الثنائي من اللوحة (قرار صفوان (أ)، 3 نوفمبر 2026 — docs/decisions/2026-11-03-2fa-admin-reset.md).
*/

beforeEach(function () {
    Notification::fake();
    $this->admin = userWithRole(RoleName::TechnicalAdmin);
});

function resetInput(array $overrides = []): array
{
    return [...['password' => 'password', 'reason' => 'فقد جواله وتطبيق المصادقة، بطلبه هاتفياً'], ...$overrides];
}

/**
 * يعيّن صاحب الحساب كلمة مرور جديدة من الرابط المرسل إليه بعد إعادة الضبط، ويُعيدها.
 */
function setPasswordFromResetLink(User $user): string
{
    $token = null;
    Notification::assertSentTo($user, TwoFactorResetByAdmin::class, function (TwoFactorResetByAdmin $notification) use (&$token): bool {
        $token = $notification->token;

        return true;
    });

    $password = 'Brand-New-Pass-2026';
    test()->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => $password, 'password_confirmation' => $password])
        ->assertRedirect('/login');

    return $password;
}

it('lets a system manager reset two-factor from the user page, ending the sessions, notifying the holder and logging without secrets', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $secret = $editor->two_factor_secret;
    $codes = $editor->two_factor_recovery_codes;
    $remember = $editor->remember_token;

    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
        ->assertActionVisible('resetTwoFactor')
        ->callAction('resetTwoFactor', data: resetInput())
        ->assertHasNoActionErrors()
        ->assertNotified(__('users.reset_two_factor.done_link'));

    $editor->refresh();

    expect($editor->two_factor_secret)->toBeNull()
        ->and($editor->two_factor_recovery_codes)->toBeNull()
        ->and($editor->two_factor_confirmed_at)->toBeNull()
        ->and($editor->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($editor->remember_token)->not->toBe($remember)
        ->and($editor->sessions_revoked_at)->not->toBeNull()
        // وكلمة المرور القديمة أُبطلت، ويلزمه تعيين غيرها برابط البريد
        ->and(Hash::check('password', $editor->password))->toBeFalse()
        ->and($editor->must_set_password_at)->not->toBeNull();

    Notification::assertSentTo($editor, TwoFactorResetByAdmin::class, function (TwoFactorResetByAdmin $notification) use ($editor): bool {
        $mail = $notification->toMail($editor);

        return in_array(__('users.reset_two_factor.mail.line'), $mail->introLines, true)
            && in_array(__('users.reset_two_factor.mail.password_line'), $mail->introLines, true)
            && $mail->actionUrl === url(route('password.reset', ['token' => $notification->token, 'email' => $editor->email], false));
    });
    expect(new TwoFactorResetByAdmin('token'))->toBeInstanceOf(ShouldQueueAfterCommit::class)
        ->and(new TwoFactorResetByAdmin('token'))->toBeInstanceOf(ShouldBeEncrypted::class);

    $log = Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->sole();
    $stored = json_encode($log->getAttributes(), JSON_UNESCAPED_UNICODE);

    expect($log->causer_id)->toBe($this->admin->id)
        ->and($log->subject_id)->toBe($editor->id)
        ->and($log->log_name)->toBe('security')
        ->and($log->properties->all())->toBe(['reason' => resetInput()['reason']])
        ->and($stored)->not->toContain((string) $secret)
        ->and($stored)->not->toContain((string) $codes)
        ->and($stored)->not->toContain((string) $this->admin->password)
        ->and($stored)->not->toContain('JBSWY3DPEHPK3PXP')
        // ولا يسجّل حفظُ الحساب نفسه شيئاً من السر
        ->and(Activity::where('subject_id', $editor->id)->get()->pluck('properties')->toJson())->not->toContain((string) $secret);
});

it('ends every session of the account that began before the reset, whatever the session store', function () {
    $member = User::factory()->withTwoFactor()->create();
    $before = now()->getTimestamp();

    $this->travel(1)->seconds();
    app(ResetTwoFactorByAdmin::class)->handle($this->admin, $member, resetInput());

    // جلسة بدأت قبل إعادة الضبط، وجلسة بلا وقت دخول (أقدم من الحقل)
    $this->actingAs($member)->withSession([EnsureAccountIsActive::LOGGED_IN_AT => $before])
        ->get('/account')->assertRedirect(route('login'));
    $this->assertGuest();

    $this->actingAs($member->fresh())->get('/account')->assertRedirect(route('login'));
    $this->assertGuest();

    // ودخول بعدها بكلمة المرور الجديدة من الرابط يبقى
    $this->travel(1)->seconds();
    $password = setPasswordFromResetLink($member);
    $this->post('/login', ['login' => $member->email, 'password' => $password])->assertRedirect('/account');
    expect($this->get('/account')->headers->get('Location'))->not->toBe(route('login'));
    $this->assertAuthenticatedAs($member);
});

it('sends a panel role back to enable two-factor before the panel after a reset', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput());

    $this->travel(1)->seconds();
    $password = setPasswordFromResetLink($editor);
    $this->post('/login', ['login' => $editor->email, 'password' => $password])->assertRedirect();
    $this->assertAuthenticatedAs($editor);

    $this->get('/admin')->assertRedirect(route('account.security'));
});

it('keeps a system manager from resetting another system manager, and lets a manager reset any lower account', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $member = User::factory()->withTwoFactor()->create();

    // لا استثناء لمديري النظام: لا يعيده أحد لمن في رتبته (مواصفة صفوان، قرار المشرف 3 نوفمبر 2026)
    expect($director->can('resetTwoFactor', $this->admin))->toBeFalse()
        ->and($this->admin->can('resetTwoFactor', $director))->toBeFalse()
        ->and($this->admin->can('resetTwoFactor', userWithRole(RoleName::TechnicalAdmin)))->toBeFalse()
        ->and(fn () => app(ResetTwoFactorByAdmin::class)->handle($director, $this->admin, resetInput()))->toThrow(AuthorizationException::class)
        ->and($this->admin->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($this->admin->can('resetTwoFactor', $superAdmin))->toBeTrue()
        ->and($director->can('resetTwoFactor', userWithRole(RoleName::ContentEditor)))->toBeTrue()
        ->and($director->can('resetTwoFactor', $member))->toBeTrue();

    $this->actingAs($director);
    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])->assertActionHidden('resetTwoFactor');
});

it('locks the reset after five wrong passwords, refusing even the right one until the lock expires', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $reset = app(ResetTwoFactorByAdmin::class);

    foreach (range(1, 5) as $ignored) {
        expect(fn () => $reset->handle($this->admin, $editor, resetInput(['password' => 'wrong-password'])))
            ->toThrow(ValidationException::class, __('auth.password'));
    }

    expect(fn () => $reset->handle($this->admin, $editor, resetInput()))
        ->toThrow(ValidationException::class, __('account.confirm_password.throttle', ['minutes' => 15]))
        ->and($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and(Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->exists())->toBeFalse();
    Notification::assertNothingSent();

    // والقفل نفسه في اللوحة
    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
        ->callAction('resetTwoFactor', data: resetInput())
        ->assertNotified(__('account.confirm_password.throttle', ['minutes' => 15]));
    expect($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    // وبعد انقضاء القفل تُقبل الصحيحة
    $this->travel(16)->minutes();
    $reset->handle($this->admin, $editor, resetInput());

    expect($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeFalse();
});

it('resets a system manager from the server console, logging the console as the executor', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $remember = $director->remember_token;

    $this->artisan('ejaz:reset-two-factor', ['email' => mb_strtoupper($director->email), '--reason' => 'فقد تطبيق المصادقة، بطلبه حضورياً', '--force' => true, '--no-interaction' => true])
        ->assertSuccessful();

    $director->refresh();

    expect($director->two_factor_secret)->toBeNull()
        ->and($director->two_factor_recovery_codes)->toBeNull()
        ->and($director->two_factor_confirmed_at)->toBeNull()
        ->and($director->remember_token)->not->toBe($remember)
        ->and($director->sessions_revoked_at)->not->toBeNull()
        ->and(Hash::check('password', $director->password))->toBeFalse()
        ->and($director->must_set_password_at)->not->toBeNull();

    Notification::assertSentTo($director, TwoFactorResetByAdmin::class);

    // ومسار الطرفية يُبطل كلمة المرور كاللوحة: القديمة مرفوضة
    $this->travel(1)->seconds();
    $this->post('/login', ['login' => $director->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();

    $log = Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->sole();

    expect($log->causer_id)->toBeNull()
        ->and($log->subject_id)->toBe($director->id)
        ->and($log->log_name)->toBe('security')
        ->and($log->properties->all())->toBe(['reason' => 'فقد تطبيق المصادقة، بطلبه حضورياً', 'via' => ResetTwoFactorByAdmin::VIA_CONSOLE])
        ->and(ActivityResource::causerPlaceholder($log))->toBe('الطرفية');
});

it('asks for confirmation on the console, and does nothing without --force under --no-interaction', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);

    $this->artisan('ejaz:reset-two-factor', ['email' => $director->email, '--reason' => 'سبب', '--no-interaction' => true])
        ->assertFailed();

    $this->artisan('ejaz:reset-two-factor', ['email' => $director->email, '--reason' => 'سبب'])
        ->expectsConfirmation('يُحذف التحقق الثنائي لحساب «'.$director->name.'» ('.$director->email.')، وتُبطَل كلمة مروره، وتنتهي كل جلساته، ويُرسل رابط تعيين كلمة المرور إلى بريده الموثق. أتابع؟', 'no')
        ->assertFailed();

    expect($director->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and(Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('refuses on the console an unknown email, a missing or long reason, and an account without two-factor', function (Closure $email, ?string $reason) {
    $this->artisan('ejaz:reset-two-factor', ['email' => $email(), '--reason' => $reason, '--force' => true, '--no-interaction' => true])
        ->assertFailed();

    expect(Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->exists())->toBeFalse();
    Notification::assertNothingSent();
})->with([
    'unknown email' => [fn () => 'nobody@example.com', 'سبب'],
    'no reason' => [fn () => userWithRole(RoleName::ExecutiveDirector)->email, null],
    'too long' => [fn () => userWithRole(RoleName::ExecutiveDirector)->email, str_repeat('س', 256)],
    'no two-factor' => [fn () => User::factory()->create()->email, 'سبب'],
    'anonymized' => [fn () => User::factory()->withTwoFactor()->create(['anonymized_at' => now()])->email, 'سبب'],
    'no verified email' => [fn () => User::factory()->withTwoFactor()->unverified()->create()->email, 'سبب'],
]);

it('refuses an editor, the general supervisor and any role without the permission', function (RoleName $role) {
    $target = User::factory()->withTwoFactor()->create();
    $actor = userWithRole($role);

    expect($actor->can('resetTwoFactor', $target))->toBeFalse()
        ->and(fn () => app(ResetTwoFactorByAdmin::class)->handle($actor, $target, resetInput()))->toThrow(AuthorizationException::class)
        ->and($target->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
})->with([
    'content editor' => [RoleName::ContentEditor],
    'general supervisor' => [RoleName::SuperAdmin],
    'secretary' => [RoleName::Secretary],
    'board member' => [RoleName::BoardMember],
    'member' => [RoleName::Member],
]);

it('keeps the general supervisor out even with the permission granted directly', function () {
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $superAdmin->givePermissionTo('users.reset_two_factor');

    expect($superAdmin->can('resetTwoFactor', User::factory()->withTwoFactor()->create()))->toBeFalse();
});

it('keeps visitors out of the user page', function () {
    $target = userWithRole(RoleName::ContentEditor);

    $this->get(UserResource::getUrl('edit', ['record' => $target]))->assertRedirect();
    $this->assertGuest();
});

it('refuses resetting your own account', function () {
    expect($this->admin->can('resetTwoFactor', $this->admin))->toBeFalse()
        ->and(fn () => app(ResetTwoFactorByAdmin::class)->handle($this->admin, $this->admin, resetInput()))->toThrow(AuthorizationException::class);

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $this->admin->getRouteKey()])->assertActionHidden('resetTwoFactor');
});

it('refuses a lower role resetting a higher or equal one, even with the permission', function () {
    $secretary = userWithRole(RoleName::Secretary);
    $secretary->givePermissionTo('users.reset_two_factor');

    expect($secretary->can('resetTwoFactor', userWithRole(RoleName::ExecutiveDirector)))->toBeFalse()
        ->and($secretary->can('resetTwoFactor', userWithRole(RoleName::TechnicalAdmin)))->toBeFalse()
        ->and($secretary->can('resetTwoFactor', userWithRole(RoleName::ContentEditor)))->toBeFalse()
        ->and($secretary->can('resetTwoFactor', User::factory()->withTwoFactor()->create()))->toBeTrue()
        ->and(fn () => app(ResetTwoFactorByAdmin::class)->handle($secretary, userWithRole(RoleName::ExecutiveDirector), resetInput()))
        ->toThrow(AuthorizationException::class);
});

it('refuses a wrong password, and changes nothing', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput(['password' => 'wrong-password'])))
        ->toThrow(ValidationException::class);

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
        ->callAction('resetTwoFactor', data: resetInput(['password' => 'wrong-password']))
        ->assertNotified(__('auth.password'));

    expect($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($editor->fresh()->sessions_revoked_at)->toBeNull()
        ->and(Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('requires a reason of at most 255 characters', function (string $reason, string $rule) {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput(['reason' => $reason])))
        ->toThrow(ValidationException::class);

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $editor->getRouteKey()])
        ->callAction('resetTwoFactor', data: resetInput(['reason' => $reason]))
        ->assertHasActionErrors(['reason' => $rule]);

    expect($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
})->with([
    'empty' => ['', 'required'],
    'too long' => [str_repeat('س', 256), 'max'],
]);

it('hides the button when the account has no two-factor enabled', function () {
    $member = User::factory()->create();

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])->assertActionHidden('resetTwoFactor');

    expect(fn () => app(ResetTwoFactorByAdmin::class)->handle($this->admin, $member, resetInput()))->toThrow(AuthorizationException::class);
});

it('refuses the sixth reset within the hour by the same manager', function () {
    $reset = app(ResetTwoFactorByAdmin::class);

    foreach (range(1, ResetTwoFactorByAdmin::MAX_PER_HOUR) as $ignored) {
        $reset->handle($this->admin, User::factory()->withTwoFactor()->create(), resetInput());
    }

    $sixth = User::factory()->withTwoFactor()->create();

    expect(fn () => $reset->handle($this->admin, $sixth, resetInput()))
        ->toThrow(ValidationException::class, __('users.reset_two_factor.throttled', ['minutes' => 60]))
        ->and($sixth->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();

    // ومدير النظام الآخر لا يشاركه الحد، وبعد الساعة يعود
    $reset->handle(userWithRole(RoleName::ExecutiveDirector), $sixth, resetInput());
    $this->travel(61)->minutes();
    $reset->handle($this->admin, User::factory()->withTwoFactor()->create(), resetInput());
});

it('ends an old panel session of a reset staff account too', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $before = now()->getTimestamp();

    $this->travel(1)->seconds();
    app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput());

    $this->actingAs($editor)->withSession([EnsureAccountIsActive::LOGGED_IN_AT => $before])
        ->get('/admin')->assertRedirect(route('login'));
    $this->assertGuest();
});

it('keeps the edit page itself from the editor and the general supervisor', function (RoleName $role) {
    $target = userWithRole(RoleName::ContentEditor);

    $this->actingAs(userWithRole($role))->get(UserResource::getUrl('edit', ['record' => $target]))->assertForbidden();
})->with([
    'content editor' => [RoleName::ContentEditor],
    'general supervisor' => [RoleName::SuperAdmin],
]);

it('does not count wrong passwords toward the hourly limit, and shows the limit in the panel', function () {
    $reset = app(ResetTwoFactorByAdmin::class);
    $first = User::factory()->withTwoFactor()->create();

    foreach (range(1, 2) as $ignored) {
        expect(fn () => $reset->handle($this->admin, $first, resetInput(['password' => 'wrong-password'])))->toThrow(ValidationException::class);
    }

    foreach (range(1, ResetTwoFactorByAdmin::MAX_PER_HOUR) as $ignored) {
        $reset->handle($this->admin, userWithRole(RoleName::ContentEditor), resetInput());
    }

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => userWithRole(RoleName::ContentEditor)->getRouteKey()])
        ->callAction('resetTwoFactor', data: resetInput())
        ->assertNotified(__('users.reset_two_factor.throttled', ['minutes' => 60]));
});

it('refuses an anonymized account', function () {
    $member = User::factory()->withTwoFactor()->create(['anonymized_at' => now()]);

    expect($this->admin->can('resetTwoFactor', $member))->toBeFalse();
});

it('mails only a verified address, and texts a verified phone when messaging is ready', function () {
    $unverified = User::factory()->withTwoFactor()->unverified()->create();

    expect((new TwoFactorResetByAdmin('token'))->via($unverified))->toBe([])
        ->and((new TwoFactorResetByAdmin('token'))->via(User::factory()->withTwoFactor()->create()))->toBe(['mail']);
});

/*
| إبطال كلمة المرور مع إعادة الضبط (قرار المشرف بعد المراجعة الأمنية، 3 نوفمبر 2026): من عرف كلمة مرور غيره وادّعى فقد
| جواله لا يدخل بها، فلا دخول ولا تطبيق مصادقة جديد قبل تعيين كلمة المرور برابط البريد الموثق.
*/

it('rejects the old password after a reset, so a claimed lost phone does not let the password alone in', function () {
    $board = User::factory()->withTwoFactor()->create();
    $board->assignRole(RoleName::BoardMember->value);

    app(ResetTwoFactorByAdmin::class)->handle(userWithRole(RoleName::ExecutiveDirector), $board, resetInput());

    $this->travel(1)->seconds();
    $this->post('/login', ['login' => $board->email, 'password' => 'password'])->assertSessionHasErrors();
    $this->assertGuest();
    expect(session()->has('login.id'))->toBeFalse();
});

it('lets the holder set a password from the link, then log in and enrol a new authenticator app', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput());
    $this->travel(1)->seconds();

    $password = setPasswordFromResetLink($editor);
    expect($editor->fresh()->must_set_password_at)->toBeNull();

    $this->post('/login', ['login' => $editor->email, 'password' => $password])->assertRedirect();
    $this->assertAuthenticatedAs($editor);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/two-factor-authentication')->assertRedirect();
    $secret = decrypt($editor->fresh()->two_factor_secret);

    $this->withSession(['auth.password_confirmed_at' => time()])
        ->post('/user/confirmed-two-factor-authentication', ['code' => (new Google2FA)->getCurrentOtp($secret)])
        ->assertSessionHasNoErrors();

    expect($editor->fresh()->hasEnabledTwoFactorAuthentication())->toBeTrue();
});

it('refuses the reset of an account without a verified email, from the action and the panel, changing nothing', function () {
    $member = User::factory()->withTwoFactor()->unverified()->create();
    $hash = $member->password;

    expect(fn () => app(ResetTwoFactorByAdmin::class)->handle($this->admin, $member, resetInput()))
        ->toThrow(ValidationException::class, __('users.reset_two_factor.no_verified_email'))
        ->and(fn () => ResetTwoFactorByAdmin::apply($member, null, 'سبب'))
        ->toThrow(ValidationException::class, __('users.reset_two_factor.no_verified_email'));

    $this->actingAs($this->admin);
    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->callAction('resetTwoFactor', data: resetInput())
        ->assertNotified(__('users.reset_two_factor.no_verified_email'));

    $member->refresh();
    expect($member->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($member->password)->toBe($hash)
        ->and($member->must_set_password_at)->toBeNull()
        ->and(Activity::where('event', ActivityEvent::TwoFactorResetByAdmin->value)->exists())->toBeFalse();
    Notification::assertNothingSent();
});

it('blocks login, open sessions and enrolling an authenticator while the flag is set, even when a password matches', function () {
    $member = User::factory()->create(['password' => 'Known-Strong-Pass-2026', 'must_set_password_at' => now()]);

    $this->post('/login', ['login' => $member->email, 'password' => 'Known-Strong-Pass-2026'])->assertSessionHasErrors();
    $this->assertGuest();

    $this->actingAs($member)->withSession([EnsureAccountIsActive::LOGGED_IN_AT => now()->getTimestamp() + 60])
        ->get('/account')->assertRedirect(route('login'))
        ->assertSessionHas('status', __('users.reset_two_factor.must_set_password'));
    $this->assertGuest();

    expect(fn () => app(EnableTwoFactorAuthentication::class)($member))->toThrow(HttpException::class)
        ->and($member->fresh()->two_factor_secret)->toBeNull();

    // وبعد رفع العلامة يدخل بكلمة المرور نفسها
    $member->forceFill(['must_set_password_at' => null])->save();
    $this->post('/login', ['login' => $member->email, 'password' => 'Known-Strong-Pass-2026'])->assertRedirect();
    $this->assertAuthenticatedAs($member);
});
