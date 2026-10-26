<?php

use App\Enums\RoleName;

/*
| مصفوفة التصدير والحذف والاطلاع على الكل (الاختبار الأمني 0.14.0): ما يُخرج بيانات شخصية من اللوحة أو يحذفها، لكل دور،
| مكتوباً هنا بعد مراجعته لا مشتقاً من ملف الصلاحيات. ومسارات GET وشاشات اللوحة وإنشاؤها وتعديلها وحذفها في
| RoleMatrixTest. والمشرف العام اطلاع فقط، فلا يصدّر ولا يحذف.
*/

const ADMINS = ['executive_director', 'technical_admin'];

it('gives each export, delete and view-all right to exactly its reviewed roles', function (string $permission, array $roles) {
    $actual = collect(RoleName::cases())
        ->filter(fn (RoleName $role) => userWithRole($role)->can($permission))
        ->map(fn (RoleName $role) => $role->value)
        ->values()
        ->all();

    expect($actual)->toEqualCanonicalizing([...ADMINS, ...$roles]);
})->with([
    'requests.export' => ['requests.export', ['secretary']],
    'programs.export' => ['programs.export', ['program_manager']],
    'submissions.export' => ['submissions.export', ['program_manager']],
    'form_entries.export' => ['form_entries.export', ['secretary']],
    'surveys.export' => ['surveys.export', ['program_manager']],
    'users.delete' => ['users.delete', []],
    'requests.delete' => ['requests.delete', ['secretary']],
    'submissions.delete' => ['submissions.delete', ['program_manager']],
    'archive.delete' => ['archive.delete', ['secretary']],
    'assembly_members.create' => ['assembly_members.create', []],
    'retention.update' => ['retention.update', []],
    'accounts.update' => ['accounts.update', []],
    'form_entries.view_all' => ['form_entries.view_all', ['super_admin', 'board_member']],
    'meetings.view_all' => ['meetings.view_all', ['super_admin', 'secretary']],
    // مسابقة الطلاب (#67): نشر النتائج والسحب والشهادات، ورقم الهوية كاملاً، وتصحيح التسجيلات
    'competition.manage_results' => ['competition.manage_results', []],
    'competition.view_national_id' => ['competition.view_national_id', []],
    'competition.correct_registrations' => ['competition.correct_registrations', []],
]);
