<?php

use App\Enums\RoleName;
use App\Models\ArchiveItem;
use App\Models\AssemblyFile;
use App\Models\Competition;
use App\Models\Document;
use App\Models\FormTemplate;
use App\Models\Meeting;
use App\Models\Program;
use App\Models\SubmissionReview;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * مصفوفة البوابات الخمس (requirements/12 §12): كل قدرة ومن يملكها، ويُفحص كل دور (عدا المشرف العام)
 * عليها: المسموح له يملكها، وغيره ممنوع. والمشرف العام يملك الكل (Gate::after) فيُفحص منفرداً.
 *
 * القدرة: [العملية، النموذج] لسياسة، أو [الصلاحية] مباشرة، أو 'panel' لدخول اللوحة.
 */
function portalMatrix(): array
{
    $employees = [RoleName::Secretary, RoleName::FinancialManager, RoleName::Accountant, RoleName::ProjectsManager, RoleName::MarketingManager];
    $panel = [RoleName::BoardMember, RoleName::ContentEditor, RoleName::ProgramManager, RoleName::Reviewer, RoleName::ExecutiveDirector, ...$employees];

    return [
        'panel access' => ['panel', $panel],

        // أ. مجلس الإدارة: اطلاع فقط
        'view meetings' => [['viewAny', Meeting::class], [RoleName::BoardMember, RoleName::Secretary]],
        'create meetings' => [['create', Meeting::class], [RoleName::Secretary]],
        'view reports (governance)' => [['viewAny', Document::class], [RoleName::BoardMember, RoleName::ContentEditor]],
        'create reports (governance)' => [['create', Document::class], [RoleName::ContentEditor]],
        'view member and board files' => [['viewAny', AssemblyFile::class], [RoleName::BoardMember, RoleName::Secretary, RoleName::ExecutiveDirector]],
        'create member and board files' => [['create', AssemblyFile::class], [RoleName::Secretary, RoleName::ExecutiveDirector]],
        'view statistics' => [['stats.view'], [RoleName::BoardMember, RoleName::ExecutiveDirector]],

        // ب. المدير التنفيذي
        'view staff accounts' => [['viewAny', User::class], [RoleName::ExecutiveDirector]],
        'create staff accounts' => [['create', User::class], [RoleName::ExecutiveDirector]],
        'assign roles' => [['assignRoles', User::class], [RoleName::ExecutiveDirector]],
        'delete accounts' => [['users.delete'], []],
        'activity log' => [['activity.view'], []],
        'create programs' => [['create', Program::class], [RoleName::ProgramManager, RoleName::ExecutiveDirector]],
        'export registrants' => [['export', Program::class], [RoleName::ProgramManager, RoleName::ExecutiveDirector]],
        'view competitions' => [['viewAny', Competition::class], [RoleName::BoardMember, RoleName::ProgramManager, RoleName::ExecutiveDirector]],
        'create competitions' => [['create', Competition::class], [RoleName::ProgramManager, RoleName::ExecutiveDirector]],
        'delete archive files' => [['archive.delete'], [RoleName::Secretary, RoleName::ExecutiveDirector]],
        'archive categories' => [['archive_categories.update'], []],
        'payments' => [['payments.view'], [RoleName::BoardMember]],

        // ج. الموظفون
        'view archive files' => [['viewAny', ArchiveItem::class], [RoleName::BoardMember, RoleName::ExecutiveDirector, ...$employees]],
        'upload archive files' => [['create', ArchiveItem::class], [RoleName::ExecutiveDirector, ...$employees]],
        'record program attendance' => [['programs.attend'], [RoleName::ProgramManager, RoleName::ExecutiveDirector, ...$employees]],
        'follow programs' => [['viewAny', Program::class], [RoleName::ProgramManager, RoleName::ExecutiveDirector, ...$employees]],
        'view forms' => [['viewAny', FormTemplate::class], [RoleName::BoardMember, RoleName::ExecutiveDirector, ...$employees]],
        'manage forms' => [['create', FormTemplate::class], [RoleName::ExecutiveDirector, ...$employees]],
        'approve filled forms' => [['form_entries.approve'], [RoleName::BoardMember]],

        // الخامسة: المحكّمون
        'view reviews' => [['viewAny', SubmissionReview::class], [RoleName::Reviewer, RoleName::ProgramManager, RoleName::ExecutiveDirector]],
        'enter scores' => [['reviews.update'], [RoleName::Reviewer]],
    ];
}

/**
 * @return array<string, array{RoleName, string|array<int, string>, bool}>
 */
function portalMatrixCases(): array
{
    $cases = [];

    foreach (portalMatrix() as $name => [$capability, $allowed]) {
        foreach (RoleName::cases() as $role) {
            if ($role === RoleName::SuperAdmin) {
                continue;
            }

            $cases["{$role->value} / {$name}"] = [$role, $capability, in_array($role, $allowed, true)];
        }
    }

    return $cases;
}

/**
 * @param  string|array<int, string>  $capability
 */
function portalCan(User $user, string|array $capability): bool
{
    if ($capability === 'panel') {
        return $user->canAccessPanel(Filament::getPanel('admin'));
    }

    return count($capability) === 1 ? $user->can($capability[0]) : $user->can($capability[0], $capability[1]);
}

it('gives each role exactly its portal capabilities', function (RoleName $role, string|array $capability, bool $expected) {
    expect(portalCan(userWithRole($role), $capability))->toBe($expected);
})->with(portalMatrixCases());

it('keeps every capability for the super admin', function () {
    $admin = userWithRole(RoleName::SuperAdmin);

    foreach (portalMatrix() as $name => [$capability]) {
        expect(portalCan($admin, $capability))->toBeTrue($name);
    }
});

it('requires two-factor authentication for every new job role, and lets them into the panel', function (RoleName $role) {
    expect(in_array($role, RoleName::twoFactorRoles(), true))->toBeTrue()
        ->and(in_array($role, RoleName::panelRoles(), true))->toBeTrue()
        ->and(in_array($role, RoleName::staff(), true))->toBeTrue();
})->with([RoleName::ExecutiveDirector, RoleName::FinancialManager, RoleName::Accountant, RoleName::ProjectsManager, RoleName::MarketingManager, RoleName::Secretary]);

it('names the staff category with the six job roles, and labels each in Arabic', function () {
    expect(RoleName::values(RoleName::staff()))->toBe([
        'executive_director', 'financial_manager', 'accountant', 'projects_manager', 'marketing_manager', 'secretary',
    ]);

    foreach (RoleName::cases() as $role) {
        expect($role->getLabel())->not->toStartWith('roles.');
    }
});
