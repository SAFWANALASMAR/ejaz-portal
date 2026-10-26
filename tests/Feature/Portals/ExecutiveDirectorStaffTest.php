<?php

use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\CreateStaffUser;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\RoleName;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/**
 * المدير التنفيذي مدير نظام كامل (المدير التنفيذي عبر صفوان، 25 أكتوبر 2026 — كان محصوراً في حسابات الموظفين منذ 10
 * أكتوبر): ينشئ الحسابات ويعدّلها ويمنح كل الأدوار عدا عضوية الجمعية العمومية، ولا يسحب دوره الإداري من نفسه. والموظفون
 * ومن سواهم لا يصعدون.
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

it('creates an account from the admin screen with every role it may grant offered', function () {
    $this->actingAs($this->director);

    Livewire::test(CreateUser::class)
        ->assertFormFieldExists('roles', fn ($field) => array_keys($field->getOptions()) === RoleName::values(RoleName::assignableBy($this->director)))
        ->fillForm(['name' => 'مسؤول تقني جديد', 'email' => 'tech@example.test', 'roles' => [RoleName::TechnicalAdmin->value]])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'tech@example.test')->sole()->hasRole(RoleName::TechnicalAdmin->value))->toBeTrue();
});

it('ignores the general assembly role, which only the members register grants', function () {
    $employee = userWithRole(RoleName::Accountant);

    app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::Accountant->value, RoleName::AssemblyMember->value]);

    expect($employee->fresh()->getRoleNames()->all())->toBe([RoleName::Accountant->value]);
});

it('keeps the general assembly membership when changing other roles', function () {
    $employee = userWithRole(RoleName::ProjectsManager);
    $employee->assignRole(RoleName::AssemblyMember->value);

    app(ChangeUserRoles::class)->handle($this->director, $employee, [RoleName::MarketingManager->value]);

    expect($employee->fresh()->getRoleNames()->sort()->values()->all())->toBe([RoleName::AssemblyMember->value, RoleName::MarketingManager->value]);
});

it('reaches every account, members and administrators alike, and lists them all', function () {
    $member = userWithRole(RoleName::Member);
    $board = userWithRole(RoleName::BoardMember);
    $super = userWithRole(RoleName::SuperAdmin);

    foreach ([$member, $board, $super] as $target) {
        expect($this->director->can('update', $target))->toBeTrue();
    }

    app(UpdateUserAccount::class)->handle($this->director, $member, ['name' => 'اسم معدّل', 'email' => $member->email]);
    expect($member->fresh()->name)->toBe('اسم معدّل');

    $this->actingAs($this->director);
    Livewire::test(ListUsers::class)->assertCanSeeTableRecords([$member, $board, $super]);
});

it('cannot remove its own administrator role', function () {
    expect(fn () => app(ChangeUserRoles::class)->handle($this->director, $this->director, [RoleName::Accountant->value]))
        ->toThrow(AuthorizationException::class);

    expect($this->director->fresh()->hasRole(RoleName::ExecutiveDirector->value))->toBeTrue();
});

it('does not let an employee escalate', function (RoleName $role) {
    $employee = userWithRole($role);
    $member = userWithRole(RoleName::Member);

    expect($employee->can('viewAny', User::class))->toBeFalse()
        ->and($employee->can('assignRoles', User::class))->toBeFalse()
        ->and(fn () => app(ChangeUserRoles::class)->handle($employee, $employee, [RoleName::TechnicalAdmin->value]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeUserRoles::class)->handle($employee, $member, [RoleName::ExecutiveDirector->value]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CreateStaffUser::class)->handle($employee, ['name' => 'آخر', 'email' => 'other@example.test'], []))->toThrow(AuthorizationException::class);

    $this->actingAs($employee)->get(UserResource::getUrl('index'))->assertForbidden();
    expect($employee->fresh()->getRoleNames()->all())->toBe([$role->value]);
})->with([RoleName::Secretary, RoleName::FinancialManager, RoleName::Accountant, RoleName::ProjectsManager, RoleName::MarketingManager]);

it('leaves role granting to the two system administrators, not the super admin nor anyone else', function () {
    $employee = userWithRole(RoleName::Accountant);

    app(ChangeUserRoles::class)->handle(userWithRole(RoleName::TechnicalAdmin), $employee, [RoleName::SuperAdmin->value]);

    expect($employee->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and(RoleName::assignableBy($this->director))->toContain(RoleName::TechnicalAdmin, RoleName::SuperAdmin)
        ->and(RoleName::assignableBy(userWithRole(RoleName::SuperAdmin)))->toBe([])
        ->and(RoleName::assignableBy(userWithRole(RoleName::Accountant)))->toBe([]);
});
