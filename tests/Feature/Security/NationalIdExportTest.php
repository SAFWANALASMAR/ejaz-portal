<?php

use App\Actions\Competitions\SaveCompetitionField;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Actions\Users\AnonymizeUser;
use App\Actions\Users\ChangeNationalIdExport;
use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\UpdateUserAccount;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\CumulativeRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\ResultsRelationManager;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CumulativeRanking;
use App\Support\CumulativeRankingSheet;
use App\Support\NationalIdExport;
use App\Support\StudentResultsSheet;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

/*
| تصدير نتائج مسابقة الطلاب برقم الهوية (ملاحظة الاختبار الشامل 10، 26 أكتوبر 2026): التصدير الافتراضي مقنّع لكل من
| يصدّر ولو كان مديراً للنظام، والكامل بصلاحية competition.export_national_id وحدها (لا منشئ المسابقة ولا من يطّلع على
| رقم واحد) وبكلمة المرور الحالية في كل مرة، ويُسجَّل بعدد السجلات ومن صدّره، وخلاياه نص لا صيغة.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->creator = userWithRole(RoleName::ProgramManager);
    $this->competition = Competition::factory()->student()->create(['slug' => 'students', 'created_by' => $this->creator->id]);
    $this->round = $this->competition->rounds()->firstOrFail();
    studentQuestion($this->round);

    foreach ([1, 2] as $serial) {
        $student = registeredStudent($this->competition, $serial, ['guardian_phone' => '055000000'.$serial]);
        app(SubmitAttempt::class)->handle($student, app(StartAttempt::class)->handle($student, $this->round));
    }
});

/**
 * @return list<list<string>>
 */
function nationalIdSheet(TestResponse|Closure $download): array
{
    $response = $download instanceof Closure ? TestResponse::fromBaseResponse($download()) : $download;
    $path = tempnam(sys_get_temp_dir(), 'n').'.xlsx';
    file_put_contents($path, $response->streamedContent());
    $reader = new Reader;
    $reader->open($path);
    $rows = [];

    foreach ($reader->getSheetIterator() as $sheet) {
        foreach ($sheet->getRowIterator() as $row) {
            $rows[] = array_map(fn ($value): string => (string) $value, $row->toArray());
        }
    }

    $reader->close();

    return $rows;
}

/**
 * التصديران (النتائج والترتيب التراكمي) بالاستعلام الذي يمرره جدولاهما.
 *
 * @return array<string, Closure(User, bool, ?string): mixed>
 */
function nationalIdExports(Competition $competition): array
{
    return [
        'results' => fn (User $user, bool $full = false, ?string $password = null) => StudentResultsSheet::download($user, $competition, $competition->attempts()->getQuery(), $full, $password),
        'cumulative' => fn (User $user, bool $full = false, ?string $password = null) => CumulativeRankingSheet::download($user, $competition, CumulativeRanking::apply(CompetitionSubmission::query(), $competition), $full, $password),
    ];
}

it('masks the national ID and guardian phone in the default export for everyone who may export, system admins included', function () {
    $resultsOnly = User::factory()->create();
    $resultsOnly->givePermissionTo('competition.manage_results');
    $exporters = [$this->director, userWithRole(RoleName::TechnicalAdmin), $this->creator, $resultsOnly];

    foreach (nationalIdExports($this->competition) as $export) {
        foreach ($exporters as $user) {
            $text = implode(' ', array_merge(...nationalIdSheet(fn () => $export($user))));

            expect($text)->not->toContain(studentNationalId(1))->not->toContain(studentNationalId(2))
                ->toContain(substr(studentNationalId(1), -4))
                ->not->toContain('0550000001')->not->toContain('055 000 0001');
        }
    }

    expect(Activity::query()->where('event', 'competition_results_exported')->pluck('properties')->pluck('full_identity')->unique()->all())->toBe([false]);
});

it('refuses the full export to whoever lacks competition.export_national_id, whatever else they hold', function () {
    // منشئ المسابقة يدير نتائجها، ومن يدير النتائج بصلاحيته وحدها لا يصدّر الكل، والمشرف العام اطلاع فقط
    $revealer = User::factory()->create();
    $revealer->givePermissionTo('competition.manage_results');
    $super = userWithRole(RoleName::SuperAdmin);

    foreach (nationalIdExports($this->competition) as $export) {
        foreach ([$this->creator, $revealer, $super] as $user) {
            expect(fn () => $export($user, true, 'password'))->toThrow(AuthorizationException::class);
        }
    }

    expect(NationalIdExport::allows($this->creator))->toBeFalse()
        ->and(NationalIdExport::allows($super))->toBeFalse()
        ->and(NationalIdExport::allows($this->director))->toBeTrue()
        ->and(NationalIdExport::allows(userWithRole(RoleName::TechnicalAdmin)))->toBeTrue()
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(0)
        // المشرف العام يُرد قبلها بإدارة النتائج، فلا يبلغ التصدير الكامل
        ->and(deniedNationalIdExports())->toBe(['permission' => 4]);
});

/**
 * المحاولات المرفوضة للتصدير الكامل في السجل، بعددها لكل سبب.
 *
 * @return array<string, int>
 */
function deniedNationalIdExports(): array
{
    return Activity::query()->where('event', 'national_id_export_denied')->get()
        // مرتبة بالسبب: ترتيب الصفوف بلا ORDER BY غير مضمون، فتفشل المقارنة بـ toBe أحياناً
        ->countBy(fn (Activity $log): string => (string) $log->properties['reason'])->sortKeys()->all();
}

it('requires the current password for every full export, and locks guessing after five wrong tries', function () {
    foreach (nationalIdExports($this->competition) as $export) {
        expect(fn () => $export($this->director, true, null))->toThrow(ValidationException::class)
            ->and(fn () => $export($this->director, true, ''))->toThrow(ValidationException::class);
    }

    // أربع خاطئة في المجموع حتى الآن، والخامسة تقفل: فلا تمر الصحيحة بعدها
    expect(fn () => nationalIdExports($this->competition)['results']($this->director, true, 'wrong'))->toThrow(ValidationException::class, __('auth.password'))
        ->and(fn () => nationalIdExports($this->competition)['results']($this->director, true, 'password'))->toThrow(ValidationException::class)
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(0)
        ->and(deniedNationalIdExports())->toBe(['locked' => 1, 'password' => 5]);
});

it('exports the national ID and guardian phone in full with the password, recording the row count and the exporter', function () {
    $rows = nationalIdSheet(fn () => nationalIdExports($this->competition)['results']($this->director, true, 'password'));
    $cumulative = nationalIdSheet(fn () => nationalIdExports($this->competition)['cumulative']($this->director, true, 'password'));

    expect($rows)->toHaveCount(3)
        ->and(array_merge(...$rows))->toContain(studentNationalId(1), studentNationalId(2), '055 000 0001')
        ->and(array_merge(...$cumulative))->toContain(studentNationalId(1), studentNationalId(2));

    $logs = Activity::query()->where('event', 'competition_results_exported')->oldest('id')->get();

    expect($logs)->toHaveCount(2);

    foreach ($logs as $log) {
        expect($log->causer_id)->toBe($this->director->id)
            ->and($log->subject_id)->toBe($this->competition->id)
            ->and($log->properties['full_identity'])->toBeTrue()
            ->and($log->properties['rows'])->toBe(2)
            ->and($log->properties['exported_by'])->toBe($this->director->name);
    }

    // اسم المصدِّر يُمسح من السجل مع إخفاء هوية حسابه، ويبقى ما صُدّر وعدده
    $this->director->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle(userWithRole(RoleName::TechnicalAdmin), $this->director);

    expect(Activity::query()->where('event', 'competition_results_exported')->get()
        ->map(fn (Activity $log): array => [$log->properties->has('exported_by'), $log->properties['rows']])->all())
        ->toBe([[false, 2], [false, 2]]);
});

it('writes every text cell of the full export as text, not as a formula', function () {
    $field = app(SaveCompetitionField::class)->handle($this->director, $this->competition, null, ['label' => 'المدرسة', 'type' => 'text']);
    $submission = $this->competition->submissions()->oldest('id')->firstOrFail();
    $submission->forceFill(['full_name' => '=HYPERLINK("http://x","y")', 'grade' => '@SUM(1)', 'extra' => [(string) $field->id => '+1+1']])->save();

    foreach (nationalIdExports($this->competition) as $export) {
        $cells = array_merge(...nationalIdSheet(fn () => $export($this->director, true, 'password')));

        expect($cells)->toContain("'=HYPERLINK(\"http://x\",\"y\")", "'@SUM(1)")
            ->and(collect($cells)->filter(fn (string $cell): bool => preg_match('/^[\s\x{00A0}]*[=+\-@]/u', $cell) === 1)->all())->toBe([]);
    }
});

it('shows the full export buttons to system admins alone, and downloads only with the right password', function () {
    $this->actingAs($this->creator);
    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertActionVisible(TestAction::make('export')->table())
        ->assertActionHidden(TestAction::make('export_full')->table());
    Livewire::test(CumulativeRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertActionVisible(TestAction::make('export_cumulative')->table())
        ->assertActionHidden(TestAction::make('export_cumulative_full')->table());

    // كل محاولة في نافذتها: الرفض يُبقي النافذة مفتوحة (halt)
    $this->actingAs($this->director);
    $results = fn () => Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class]);
    $results()->callAction(TestAction::make('export_full')->table(), ['password' => ''])
        ->assertHasFormErrors(['password' => 'required'])
        ->assertNoFileDownloaded();
    $results()->callAction(TestAction::make('export_full')->table(), ['password' => 'wrong'])
        ->assertNotified(__('auth.password'))
        ->assertNoFileDownloaded();
    $results()->callAction(TestAction::make('export_full')->table(), ['password' => 'password'])
        ->assertFileDownloaded('results-students-full.xlsx');
    Livewire::test(CumulativeRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export_cumulative_full')->table(), ['password' => 'password'])
        ->assertFileDownloaded('cumulative-students-full.xlsx');

    expect(Activity::query()->where('event', 'competition_results_exported')->pluck('properties')->pluck('full_identity')->all())->toBe([true, true]);
});

/*
| الخيار في تعديل المستخدم (قرار صفوان، 27 أكتوبر 2026): يمنحه مديرا النظام لمن يختاران مباشرة لا عبر دور، ويسحبانه،
| ويُسجَّل كل منح وسحب. ولا يراه غيرهما، ولا يُمنح للمشرف العام.
*/

it('lets a system admin grant the full export to a chosen user from the user screen, and revoke it back to masked', function () {
    $this->actingAs($this->director);
    $edit = fn () => Livewire::test(EditUser::class, ['record' => $this->creator->getRouteKey()]);
    $edit()->assertFormFieldVisible('export_national_id')
        ->assertFormSet(['export_national_id' => false])
        ->fillForm(['export_national_id' => true])
        ->call('save')
        ->assertHasNoFormErrors();

    $creator = $this->creator->fresh();
    $rows = nationalIdSheet(fn () => nationalIdExports($this->competition)['results']($creator, true, 'password'));

    expect($creator->hasDirectPermission(NationalIdExport::PERMISSION))->toBeTrue()
        ->and($creator->getRoleNames()->all())->toBe([RoleName::ProgramManager->value])
        ->and(array_merge(...$rows))->toContain(studentNationalId(1), studentNationalId(2));

    $edit()->assertFormSet(['export_national_id' => true])
        ->fillForm(['export_national_id' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    $creator = $this->creator->fresh();
    $masked = implode(' ', array_merge(...nationalIdSheet(fn () => nationalIdExports($this->competition)['results']($creator))));

    expect(NationalIdExport::allows($creator))->toBeFalse()
        ->and(fn () => nationalIdExports($this->competition)['results']($creator, true, 'password'))->toThrow(AuthorizationException::class)
        ->and($masked)->not->toContain(studentNationalId(1))->toContain(substr(studentNationalId(1), -4));

    // من منح، ولمن، ومتى: بالترتيب، ولا سجل لحفظ لم يغيّر الخيار
    $edit()->fillForm(['name' => 'اسم آخر'])->call('save');
    $logs = Activity::query()->whereIn('event', ['national_id_export_granted', 'national_id_export_revoked'])->oldest('id')->get();

    expect($logs->pluck('event')->all())->toBe(['national_id_export_granted', 'national_id_export_revoked'])
        ->and($logs->pluck('causer_id')->unique()->all())->toBe([$this->director->id])
        ->and($logs->pluck('subject_id')->unique()->all())->toBe([$this->creator->id])
        ->and($logs->every(fn (Activity $log): bool => $log->created_at !== null && $log->log_name === 'users'))->toBeTrue();
});

it('hides the option from whoever is not a system admin, and refuses it through Livewire', function () {
    // ولو مُنح users.* مباشرة: لا يصل إلى تعديل الحسابات (UserResource بلا أدوار يمنحها)، ولا يمر الإجراء
    $editor = userWithRole(RoleName::ContentEditor);
    $editor->givePermissionTo(['users.view', 'users.update']);
    $member = User::factory()->create();

    $this->actingAs($editor)->get(UserResource::getUrl('edit', ['record' => $member]))->assertNotFound();

    expect(fn () => app(ChangeNationalIdExport::class)->handle($editor, $member, true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeNationalIdExport::class)->handle($this->creator, $member, true))->toThrow(AuthorizationException::class);

    // ومخفي عن مدير النظام في حساب مدير نظام آخر (له بدوره)، فلا يمر بتعديل حالته عبر Livewire
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $this->actingAs($this->director);
    Livewire::test(EditUser::class, ['record' => $admin->getRouteKey()])
        ->assertFormFieldHidden('export_national_id')
        ->set('data.export_national_id', true)
        ->call('save')
        ->assertHasNoFormErrors();

    expect($member->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and($admin->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and(Activity::query()->where('event', 'national_id_export_granted')->count())->toBe(0);
});

it('never grants the full export to the super admin, nor lets the super admin grant it', function () {
    $super = userWithRole(RoleName::SuperAdmin);
    $member = User::factory()->create();

    expect(fn () => app(ChangeNationalIdExport::class)->handle($super, $member, true))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ChangeNationalIdExport::class)->handle($this->director, $super, true))->toThrow(ValidationException::class);

    $this->actingAs($this->director);
    Livewire::test(EditUser::class, ['record' => $super->getRouteKey()])
        ->assertFormFieldDisabled('export_national_id');

    // ولو وصلته الصلاحية مباشرة قبل دوره، فهو اطلاع فقط
    $super->givePermissionTo([NationalIdExport::PERMISSION, 'competition.manage_results']);

    expect(NationalIdExport::allows($super->fresh()))->toBeFalse()
        ->and(fn () => nationalIdExports($this->competition)['results']($super->fresh(), true, 'password'))->toThrow(AuthorizationException::class)
        ->and(Activity::query()->where('event', 'national_id_export_granted')->count())->toBe(0);
});

it('drops a direct grant of the full export when the account is anonymized', function () {
    app(ChangeNationalIdExport::class)->handle($this->director, $this->creator, true);
    $this->creator->forceFill(['deletion_requested_at' => now()])->save();
    app(AnonymizeUser::class)->handle($this->director, $this->creator);

    expect($this->creator->fresh()->getDirectPermissions())->toBeEmpty();
});

/*
| ملاحظات المشرف بعد المراجعة الأمنية المستقلة لـ 791a25a8.
*/

it('refuses the grant from a user editor who is not a system admin, rolling the whole save back', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    $editor->givePermissionTo(['users.view', 'users.update']);
    $member = User::factory()->create(['name' => 'الاسم الأول']);

    // يعدّل الحساب بسياسته، والخيار يرده الإجراء، فلا يبقى الاسم ولا المنح ولا سجل
    expect(fn () => app(UpdateUserAccount::class)->handle($editor, $member, ['name' => 'اسم جديد', 'email' => $member->email], null, true))
        ->toThrow(AuthorizationException::class);

    expect($member->fresh()->name)->toBe('الاسم الأول')
        ->and($member->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and(Activity::query()->whereIn('event', ['national_id_export_granted', 'national_id_export_revoked'])->count())->toBe(0);
});

it('never grants the full export to a deactivated account, and drops it on deactivation so reactivation does not bring it back', function () {
    $inactive = User::factory()->create(['is_active' => false]);

    expect(fn () => app(ChangeNationalIdExport::class)->handle($this->director, $inactive, true))
        ->toThrow(ValidationException::class, __('users.national_id_export.not_for_inactive'));

    app(ChangeNationalIdExport::class)->handle($this->director, $this->creator, true);
    $this->actingAs($this->director);
    $edit = fn () => Livewire::test(EditUser::class, ['record' => $this->creator->getRouteKey()]);
    $edit()->fillForm(['is_active' => false])->call('save')->assertHasNoFormErrors();

    expect($this->creator->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse();

    $edit()->assertFormSet(['export_national_id' => false])->fillForm(['is_active' => true])->call('save')->assertHasNoFormErrors();

    $revoked = Activity::query()->where('event', 'national_id_export_revoked')->sole();

    expect(NationalIdExport::allows($this->creator->fresh()))->toBeFalse()
        ->and($revoked->causer_id)->toBe($this->director->id)
        ->and($revoked->subject_id)->toBe($this->creator->id)
        ->and($revoked->properties['reason'])->toBe('deactivated');
});

it('refuses the super admin role and the option in one save, rolling both back without a log', function () {
    $member = User::factory()->create();
    $this->actingAs($this->director);

    $component = Livewire::test(EditUser::class, ['record' => $member->getRouteKey()])
        ->fillForm(['roles' => [RoleName::SuperAdmin->value], 'export_national_id' => true])
        ->call('save');

    expect($component->errors()->first('data.export_national_id'))->toBe(__('users.national_id_export.not_for_super_admin'))
        ->and($member->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeFalse()
        ->and($member->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and(Activity::query()->whereIn('event', ['roles_changed', 'national_id_export_granted', 'national_id_export_revoked'])->count())->toBe(0);
});

it('drops a direct grant when the account is given the super admin role, with a log line', function () {
    app(ChangeNationalIdExport::class)->handle($this->director, $this->creator, true);
    app(ChangeUserRoles::class)->handle($this->director, $this->creator, [RoleName::ProgramManager->value, RoleName::SuperAdmin->value]);

    $revoked = Activity::query()->where('event', 'national_id_export_revoked')->sole();

    expect($this->creator->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and($this->creator->fresh()->hasRole(RoleName::SuperAdmin->value))->toBeTrue()
        ->and($revoked->causer_id)->toBe($this->director->id)
        ->and($revoked->subject_id)->toBe($this->creator->id)
        ->and($revoked->properties['reason'])->toBe('super_admin');
});

it('names the full export file apart from the masked one', function () {
    $this->actingAs($this->director);

    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export')->table())
        ->assertFileDownloaded('results-students.xlsx');
    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export_full')->table(), ['password' => 'password'])
        ->assertFileDownloaded('results-students-full.xlsx');
});

it('removes the old single-ID permission from the database, with its grants', function () {
    $permission = Permission::findOrCreate('competition.view_national_id', 'web');
    $holder = User::factory()->create();
    $holder->givePermissionTo($permission);

    (require database_path('migrations/2026_10_28_090000_remove_view_national_id_permission.php'))->up();

    expect(Permission::query()->where('name', 'competition.view_national_id')->exists())->toBeFalse()
        ->and($holder->fresh()->getAllPermissions()->pluck('name'))->not->toContain('competition.view_national_id')
        ->and(config('permissions.modules.competition'))->toBeArray()->not->toContain('view_national_id');
});

it('refuses the full export option to a user who manages neither competition results nor competitions', function () {
    $staff = userWithRole(RoleName::Accountant);
    $plain = User::factory()->create();

    foreach ([$staff, $plain] as $user) {
        expect(fn () => app(ChangeNationalIdExport::class)->handle($this->director, $user, true))
            ->toThrow(ValidationException::class, __('users.national_id_export.not_for_non_manager'))
            ->and($user->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse();
    }

    // ومن الشاشة: رسالة على الحقل، ولا شيء يُحفظ
    $this->actingAs($this->director);
    Livewire::test(EditUser::class, ['record' => $staff->getRouteKey()])
        ->fillForm(['export_national_id' => true])
        ->call('save')
        ->assertHasFormErrors(['export_national_id']);

    expect($staff->fresh()->hasDirectPermission(NationalIdExport::PERMISSION))->toBeFalse()
        ->and(Activity::query()->where('event', 'national_id_export_granted')->count())->toBe(0);
});
