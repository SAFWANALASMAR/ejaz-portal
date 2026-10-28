<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Assembly\SaveAssemblyMembership;
use App\Actions\Services\ChangeServiceRequestStatus;
use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ChangeUserRoles;
use App\Enums\MembershipStatus;
use App\Enums\OpeningKind;
use App\Enums\RoleName;
use App\Enums\ServiceRequestStatus;
use App\Models\AssemblyMembership;
use App\Models\Opening;
use App\Models\ServiceRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| سجل أعضاء الجمعية العمومية (requirements/04): السجل وحده يمنح دور عضو الجمعية العمومية ويسحبه، وللمشرف
| العام وحده (قرار صفوان: قبول العضوية له وحده).
*/

beforeEach(function () {
    $this->admin = userWithRole(RoleName::TechnicalAdmin);
});

it('lets the super admin register a member, which grants the role, and nobody else', function () {
    $user = User::factory()->create();
    $data = ['user_id' => $user->id, 'status' => MembershipStatus::Active->value, 'joined_on' => '2026-01-15'];

    foreach ([RoleName::Secretary, RoleName::BoardMember, RoleName::ContentEditor] as $role) {
        expect(fn () => app(SaveAssemblyMembership::class)->handle(userWithRole($role), new AssemblyMembership, $data))->toThrow(AuthorizationException::class);
    }

    $membership = app(SaveAssemblyMembership::class)->handle($this->admin, new AssemblyMembership, $data);

    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->joined_on->toDateString())->toBe('2026-01-15')
        ->and($user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue()
        ->and(Activity::where('event', 'roles_changed')->where('subject_id', $user->id)->sole()->causer_id)->toBe($this->admin->id);
});

it('takes the role away when a membership is suspended or ended, and gives it back when reactivated', function () {
    $membership = AssemblyMembership::factory()->create();
    $user = $membership->user;

    app(SaveAssemblyMembership::class)->handle($this->admin, $membership, ['status' => MembershipStatus::Suspended->value]);
    expect($user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeFalse();

    app(SaveAssemblyMembership::class)->handle($this->admin, $membership->fresh(), ['status' => MembershipStatus::Active->value]);
    expect($user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue();

    app(SaveAssemblyMembership::class)->handle($this->admin, $membership->fresh(), ['status' => MembershipStatus::Ended->value]);
    expect($user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeFalse()
        ->and($membership->fresh()->status)->toBe(MembershipStatus::Ended);
});

it('refuses a second membership for one account, and an inactive or anonymized account', function () {
    $membership = AssemblyMembership::factory()->create();
    $inactive = User::factory()->create(['is_active' => false]);
    $anonymized = User::factory()->create(['anonymized_at' => now()]);

    foreach ([$membership->user, $inactive, $anonymized] as $user) {
        expect(fn () => app(SaveAssemblyMembership::class)->handle($this->admin, new AssemblyMembership, ['user_id' => $user->id, 'status' => 'active']))
            ->toThrow(ValidationException::class);
    }

    expect(AssemblyMembership::count())->toBe(1);
});

it('registers the member when the super admin accepts a membership request', function () {
    $request = ServiceRequest::factory()->for(Opening::factory()->create(['kind' => OpeningKind::Membership]))->create();

    app(ChangeServiceRequestStatus::class)->handle($this->admin, $request, ServiceRequestStatus::Accepted);

    $membership = AssemblyMembership::where('user_id', $request->user_id)->sole();
    expect($membership->status)->toBe(MembershipStatus::Active)
        ->and($membership->joined_on->isToday())->toBeTrue()
        ->and($membership->service_request_id)->toBe($request->id)
        ->and($request->user->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue();
});

it('leaves the member role to the registry when roles change on the users screen', function () {
    $membership = AssemblyMembership::factory()->create();
    $member = $membership->user;
    $outsider = User::factory()->create();

    // من له العضوية لا تُسحب منه بشاشة المستخدمين، ومن ليس له لا تُمنح بها
    app(ChangeUserRoles::class)->handle($this->admin, $member, [RoleName::Member->value]);
    app(ChangeUserRoles::class)->handle($this->admin, $outsider, [RoleName::AssemblyMember->value]);

    expect($member->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeTrue()
        ->and($outsider->fresh()->hasRole(RoleName::AssemblyMember->value))->toBeFalse()
        ->and($outsider->fresh()->hasRole(RoleName::Member->value))->toBeTrue();
});

it('ends the membership of an anonymized account, and includes it in the personal data copy', function () {
    $membership = AssemblyMembership::factory()->create(['joined_on' => '2025-03-01']);
    $member = $membership->user;

    expect(app(ExportUserData::class)->handle($member)['assembly_membership'])
        ->toBe(['status' => 'active', 'joined_on' => '2025-03-01', 'meetings_attended' => []]);

    $member->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle($this->admin, $member);

    expect($membership->fresh()->status)->toBe(MembershipStatus::Ended);
});

it('backfills the registry from the member role held before it', function () {
    // الترحيل يسجّل كل من له الدور عضواً فعّالاً بلا تاريخ، ولا يكرر
    $migration = require database_path('migrations/2026_10_09_120000_create_assembly_tables.php');
    $holder = userWithRole(RoleName::AssemblyMember);
    AssemblyMembership::query()->delete();

    // ترحيل السجلات يبني فوق جدول الاجتماعات، فيُرجَع قبله ويُعاد بعده
    $registers = require database_path('migrations/2026_10_10_200000_create_registers_tables.php');
    $registers->down();
    $migration->down();
    $migration->up();
    $registers->up();

    expect(AssemblyMembership::where('user_id', $holder->id)->sole())
        ->status->toBe(MembershipStatus::Active)
        ->joined_on->toBeNull();
});
