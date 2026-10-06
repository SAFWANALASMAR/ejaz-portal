<?php

use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(fn () => $this->admin = userWithRole(RoleName::SuperAdmin));

it('keeps the users screen for those allowed to see personal data', function () {
    $this->actingAs($this->admin)->get(UserResource::getUrl('index'))->assertOk();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(UserResource::getUrl('index'))->assertForbidden();
    $this->actingAs(userWithRole(RoleName::Secretary))->get(UserResource::getUrl('index'))->assertForbidden();
});

it('creates a staff account that sets its own password by email', function () {
    Notification::fake();
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'موظف الجمعية',
            'email' => 'Staff@Example.test',
            'phone' => '0512345678',
            'roles' => [RoleName::ContentEditor->value],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $staff = User::where('email', 'staff@example.test')->firstOrFail();

    expect($staff->phone)->toBe('+966512345678')
        ->and($staff->hasRole(RoleName::ContentEditor->value))->toBeTrue();

    Notification::assertSentTo($staff, ResetPasswordNotification::class);
});

it('rejects an email or phone already in use, whatever the spelling', function () {
    User::factory()->create(['email' => 'taken@example.test', 'phone' => '+966512345678']);
    $this->actingAs($this->admin);

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'شخص جديد', 'email' => 'TAKEN@example.test', 'phone' => '٠٥١٢٣٤٥٦٧٨'])
        ->call('create')
        ->assertHasFormErrors(['email', 'phone']);
});

it('lets only the super admin change roles, and logs the change', function () {
    $member = User::factory()->create();
    $member->assignRole(RoleName::Member->value);

    app(ChangeUserRoles::class)->handle($this->admin, $member, [RoleName::Secretary->value]);

    expect($member->fresh()->hasRole(RoleName::Secretary->value))->toBeTrue();

    $log = Activity::where('event', 'roles_changed')->firstOrFail();
    expect($log->properties['old'])->toBe([RoleName::Member->value])
        ->and($log->properties['new'])->toBe([RoleName::Secretary->value]);

    $editor = userWithRole(RoleName::ContentEditor);
    expect(fn () => app(ChangeUserRoles::class)->handle($editor, $member, [RoleName::SuperAdmin->value]))
        ->toThrow(AuthorizationException::class);
});

it('refuses to create a staff account without permission, wherever it is called from', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    expect(fn () => app(CreateStaffUser::class)->handle($editor, ['name' => 'موظف', 'email' => 'x@example.test'], []))
        ->toThrow(AuthorizationException::class);

    expect(User::where('email', 'x@example.test')->exists())->toBeFalse();
});

it('drops the verification of a phone or email changed by an administrator', function () {
    Notification::fake();
    $member = User::factory()->create([
        'email' => 'member@example.test',
        'phone' => '+966512345678',
        'phone_verified_at' => now(),
    ]);

    app(UpdateUserAccount::class)->handle($this->admin, $member, [
        'name' => $member->name,
        'email' => 'member@example.test',
        'phone' => '+966598765432',
    ]);

    expect($member->fresh()->phone_verified_at)->toBeNull()
        ->and($member->fresh()->email_verified_at)->not->toBeNull();

    app(UpdateUserAccount::class)->handle($this->admin, $member->fresh(), [
        'name' => $member->name,
        'email' => 'new@example.test',
        'phone' => '+966598765432',
    ]);

    expect($member->fresh()->email_verified_at)->toBeNull();
});

it('keeps the verification when the phone and email do not change', function () {
    $member = User::factory()->create(['phone' => '+966512345678', 'phone_verified_at' => now()]);

    app(UpdateUserAccount::class)->handle($this->admin, $member, [
        'name' => 'اسم جديد',
        'email' => $member->email,
        'phone' => '+966512345678',
    ]);

    expect($member->fresh()->hasVerifiedPhone())->toBeTrue()
        ->and($member->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('does not let anyone deactivate their own account', function () {
    expect(fn () => app(UpdateUserAccount::class)->handle($this->admin, $this->admin, [
        'name' => $this->admin->name,
        'email' => $this->admin->email,
        'is_active' => false,
    ]))->toThrow(AuthorizationException::class);

    expect($this->admin->fresh()->is_active)->toBeTrue();
});

it('deactivates another account from the edit screen', function () {
    $member = User::factory()->create();
    $this->actingAs($this->admin);

    Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->fillForm(['is_active' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->is_active)->toBeFalse();
});

it('anonymizes an account whose owner asked for deletion, and only then', function () {
    $requester = User::factory()->create([
        'name' => 'صاحب الطلب',
        'email' => 'requester@example.test',
        'phone' => '+966512345678',
        'deletion_requested_at' => now(),
    ]);
    $requester->assignRole(RoleName::Member->value);
    $other = User::factory()->create();

    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)
        ->assertActionHidden(TestAction::make('anonymize')->table($other))
        ->callAction(TestAction::make('anonymize')->table($requester));

    $requester->refresh();

    expect($requester->name)->toBe(__('users.anonymized_name'))
        ->and($requester->email)->toBe('deleted-'.$requester->id.'@deleted.invalid')
        ->and($requester->phone)->toBeNull()
        ->and($requester->is_active)->toBeFalse()
        ->and($requester->anonymized_at)->not->toBeNull()
        ->and($requester->getRoleNames())->toBeEmpty()
        ->and(Activity::where('event', 'anonymized')->exists())->toBeTrue();
});

it('refuses to anonymize without a request, twice, or one\'s own account', function () {
    $noRequest = User::factory()->create();
    $done = User::factory()->create(['deletion_requested_at' => now(), 'anonymized_at' => now()]);
    $this->admin->forceFill(['deletion_requested_at' => now()])->save();

    foreach ([$noRequest, $done, $this->admin] as $target) {
        expect(fn () => app(AnonymizeUser::class)->handle($this->admin, $target))->toThrow(AuthorizationException::class);
    }
});
