<?php

use App\Actions\Users\ResetTwoFactorByAdmin;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Models\User;
use App\Notifications\TwoFactorResetByAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

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
        ->assertNotified(__('users.reset_two_factor.done'));

    $editor->refresh();

    expect($editor->two_factor_secret)->toBeNull()
        ->and($editor->two_factor_recovery_codes)->toBeNull()
        ->and($editor->two_factor_confirmed_at)->toBeNull()
        ->and($editor->hasEnabledTwoFactorAuthentication())->toBeFalse()
        ->and($editor->remember_token)->not->toBe($remember)
        ->and($editor->sessions_revoked_at)->not->toBeNull();

    Notification::assertSentTo($editor, TwoFactorResetByAdmin::class, function (TwoFactorResetByAdmin $notification) use ($editor): bool {
        $mail = $notification->toMail($editor);

        return in_array(__('users.reset_two_factor.mail.line'), $mail->introLines, true)
            && $mail->actionUrl === route('contact');
    });
    expect(new TwoFactorResetByAdmin)->toBeInstanceOf(ShouldQueueAfterCommit::class);

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

    // ودخول بعدها يبقى
    $this->travel(1)->seconds();
    $this->post('/login', ['login' => $member->email, 'password' => 'password'])->assertRedirect('/account');
    // (وتحويل كلمة المرور الضعيفة في المصنع إلى تغييرها لا يخرجه)
    expect($this->get('/account')->headers->get('Location'))->not->toBe(route('login'));
    $this->assertAuthenticatedAs($member);
});

it('sends a panel role back to enable two-factor before the panel after a reset', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    app(ResetTwoFactorByAdmin::class)->handle($this->admin, $editor, resetInput());

    $this->travel(1)->seconds();
    $this->post('/login', ['login' => $editor->email, 'password' => 'password'])->assertRedirect();
    $this->assertAuthenticatedAs($editor);

    $this->get('/admin')->assertRedirect(route('account.security'));
});

it('lets the two system managers reset each other, and a manager reset any lower account', function () {
    $director = userWithRole(RoleName::ExecutiveDirector);
    $superAdmin = userWithRole(RoleName::SuperAdmin);
    $member = User::factory()->withTwoFactor()->create();

    expect($director->can('resetTwoFactor', $this->admin))->toBeTrue()
        ->and($this->admin->can('resetTwoFactor', $director))->toBeTrue()
        ->and($this->admin->can('resetTwoFactor', userWithRole(RoleName::TechnicalAdmin)))->toBeTrue()
        ->and($this->admin->can('resetTwoFactor', $superAdmin))->toBeTrue()
        ->and($director->can('resetTwoFactor', $member))->toBeTrue();
});

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

    expect((new TwoFactorResetByAdmin)->via($unverified))->toBe([])
        ->and((new TwoFactorResetByAdmin)->via(User::factory()->withTwoFactor()->create()))->toBe(['mail']);
});
