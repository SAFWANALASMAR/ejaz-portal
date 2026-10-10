<?php

use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * «إدارة الموظفين» للمدير التنفيذي (requirements/12 §12 ب) دون تصعيد: يمنح أدوار الموظفين وحدها،
 * ولا يصل إلى غير حسابات الموظفين. والمشرف العام وحده يمنح دور المشرف العام.
 */
beforeEach(function () {
    Notification::fake();
    $this->director = userWithRole(RoleName::ExecutiveDirector);
});

it('creates an employee account with a job role, invites it and logs the roles', function () {
    $created = app(CreateStaffUser::class)->handle($this->director, ['name' => 'موظف جديد', 'email' => 'staff@example.test'], [RoleName::Accountant->value]);

    expect($created->getRoleNames()->all())->toBe([RoleName::Accountant->value])
        ->and(Activity::where('event', 'roles_changed')->where('subject_id', $created->id)->where('causer_id', $this->director->id)->exists())->toBeTrue();
});

it('creates an employee from the admin screen with only the roles it may grant offered', function () {
    $this->actingAs($this->director);

    Livewire::test(CreateUser::class)
        ->assertFormFieldExists('roles', fn ($field) => array_keys($field->getOptions()) === RoleName::values(RoleName::assignableBy($this->director)))
        ->fillForm(['name' => 'موظفة جديدة', 'email' => 'new.staff@example.test', 'roles' => [RoleName::MarketingManager->value]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'new.staff@example.test')->sole()->hasRole(RoleName::MarketingManager->value))->toBeTrue();
});

it('never grants the super admin, board or executive director roles', function (RoleName $role) {
    expect(fn () => app(CreateStaffUser::class)->handle($this->director, ['name' => 'محاولة', 'email' => 'escalate@example.test'], [$role->value]))
        ->toThrow(AuthorizationException::class);

    expect(User::where('email', 'escalate@example.test')->exists())->toBeFalse();

    $employee = userWithRole(RoleName::FinancialManager);

    expect(fn () => app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::FinancialManager->value, $role->value]))
        ->toThrow(AuthorizationException::class)
        ->and($employee->fresh()->getRoleNames()->all())->toBe([RoleName::FinancialManager->value]);
})->with([RoleName::SuperAdmin, RoleName::BoardMember, RoleName::ExecutiveDirector, RoleName::Reviewer]);

it('ignores the general assembly role, which only the members register grants', function () {
    $employee = userWithRole(RoleName::Accountant);

    app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::Accountant->value, RoleName::AssemblyMember->value]);

    expect($employee->fresh()->getRoleNames()->all())->toBe([RoleName::Accountant->value]);
});

it('refuses the super admin role sent through the edit form', function () {
    $employee = userWithRole(RoleName::Accountant);
    $this->actingAs($this->director);

    Livewire::test(EditUser::class, ['record' => $employee->getRouteKey()])
        ->fillForm(['roles' => [RoleName::SuperAdmin->value]])
        ->call('save');

    expect($employee->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeFalse();
});

it('changes an employee job and logs it', function () {
    $employee = userWithRole(RoleName::Accountant);

    app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::FinancialManager->value]);

    expect($employee->fresh()->getRoleNames()->all())->toBe([RoleName::FinancialManager->value]);

    $log = Activity::where('event', 'roles_changed')->sole();
    expect($log->properties['old'])->toBe([RoleName::Accountant->value])
        ->and($log->properties['new'])->toBe([RoleName::FinancialManager->value]);
});

it('keeps the general assembly membership that it does not manage', function () {
    $employee = userWithRole(RoleName::ProjectsManager);
    $employee->assignRole(RoleName::AssemblyMember->value);

    app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::MarketingManager->value]);

    expect($employee->fresh()->getRoleNames()->sort()->values()->all())->toBe([RoleName::AssemblyMember->value, RoleName::MarketingManager->value]);
});

it('edits an employee account', function () {
    $employee = userWithRole(RoleName::Accountant);

    app(UpdateUserAccount::class)->handle($this->director, $employee, ['name' => 'اسم معدّل', 'email' => $employee->email]);

    expect($employee->fresh()->name)->toBe('اسم معدّل');
});

it('cannot reach the accounts of the super admin, the board, another director, reviewers or members', function (Closure $make) {
    /** @var User $target */
    $target = $make();

    expect($this->director->can('view', $target))->toBeFalse()
        ->and($this->director->can('update', $target))->toBeFalse()
        ->and($this->director->can('assignRoles', $target))->toBeFalse()
        ->and(fn () => app(UpdateUserAccount::class)->handle($this->director, $target, ['name' => 'تغيير', 'email' => $target->email]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeUserRoles::class)->handle($this->director, $target, [RoleName::Accountant->value]))->toThrow(AuthorizationException::class);

    // خارج قائمته أصلاً (404)، والسياسة ترفض لو وصل (403)
    expect($this->actingAs($this->director)->get(UserResource::getUrl('edit', ['record' => $target]))->status())->toBeIn([403, 404]);
})->with([
    'super admin' => fn () => userWithRole(RoleName::SuperAdmin),
    'board member' => fn () => userWithRole(RoleName::BoardMember),
    'another director' => fn () => userWithRole(RoleName::ExecutiveDirector),
    'reviewer' => fn () => userWithRole(RoleName::Reviewer),
    'employee who is also on the board' => function () {
        $user = userWithRole(RoleName::Accountant);
        $user->assignRole(RoleName::BoardMember->value);

        return $user;
    },
    'member' => fn () => userWithRole(RoleName::Member),
]);

it('cannot edit or promote their own account', function () {
    expect($this->director->can('update', $this->director))->toBeFalse()
        ->and(fn () => app(ChangeUserRoles::class)->handle($this->director, $this->director, [RoleName::Accountant->value]))->toThrow(AuthorizationException::class);
});

it('lists only the employee accounts it manages', function () {
    $employee = userWithRole(RoleName::Accountant);
    $editor = userWithRole(RoleName::ContentEditor);
    $member = userWithRole(RoleName::Member);
    $admin = userWithRole(RoleName::SuperAdmin);
    $board = userWithRole(RoleName::BoardMember);

    $this->actingAs($this->director);

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$employee, $editor])
        ->assertCanNotSeeTableRecords([$member, $admin, $board, $this->director]);
});

it('keeps the full list for the super admin', function () {
    $member = userWithRole(RoleName::Member);
    $this->actingAs(userWithRole(RoleName::SuperAdmin));

    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$member, $this->director]);
});

it('cannot delete or anonymize accounts', function () {
    $employee = userWithRole(RoleName::Accountant);
    $employee->forceFill(['deletion_requested_at' => now()])->save();

    expect($this->director->can('delete', $employee))->toBeFalse()
        ->and($this->director->can('anonymize', $employee))->toBeFalse();
});

it('does not let an employee escalate', function (RoleName $role) {
    $employee = userWithRole($role);
    $member = userWithRole(RoleName::Member);

    expect($employee->can('viewAny', User::class))->toBeFalse()
        ->and($employee->can('assignRoles', User::class))->toBeFalse()
        ->and(fn () => app(ChangeUserRoles::class)->handle($employee, $employee, [RoleName::SuperAdmin->value]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeUserRoles::class)->handle($employee, $member, [RoleName::ExecutiveDirector->value]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateStaffUser::class)->handle($employee, ['name' => 'آخر', 'email' => 'other@example.test'], []))->toThrow(AuthorizationException::class);

    $this->actingAs($employee)->get(UserResource::getUrl('index'))->assertForbidden();
    expect($employee->fresh()->getRoleNames()->all())->toBe([$role->value]);
})->with([RoleName::Secretary, RoleName::FinancialManager, RoleName::Accountant, RoleName::ProjectsManager, RoleName::MarketingManager]);

it('leaves the super admin as the only one who grants the super admin role', function () {
    $admin = userWithRole(RoleName::SuperAdmin);
    $employee = userWithRole(RoleName::Accountant);

    app(ChangeUserRoles::class)->handle($admin, $employee, [RoleName::SuperAdmin->value]);

    expect($employee->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and(RoleName::assignableBy($this->director))->not->toContain(RoleName::SuperAdmin)
        ->and(RoleName::assignableBy(userWithRole(RoleName::Accountant)))->toBe([]);
});
