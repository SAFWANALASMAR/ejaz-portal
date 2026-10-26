<?php

use App\Actions\Competitions\SaveCompetitionField;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\CumulativeRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\ResultsRelationManager;
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
    $resultsOnly->givePermissionTo(['competition.manage_results', 'competition.view_national_id']);
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
    // منشئ المسابقة يدير نتائجها، ومن يطّلع على رقم واحد بسجله لا يصدّر الكل، والمشرف العام اطلاع فقط
    $revealer = User::factory()->create();
    $revealer->givePermissionTo(['competition.manage_results', 'competition.view_national_id']);
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
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(0);
});

it('requires the current password for every full export, and locks guessing after five wrong tries', function () {
    foreach (nationalIdExports($this->competition) as $export) {
        expect(fn () => $export($this->director, true, null))->toThrow(ValidationException::class)
            ->and(fn () => $export($this->director, true, ''))->toThrow(ValidationException::class);
    }

    // أربع خاطئة في المجموع حتى الآن، والخامسة تقفل: فلا تمر الصحيحة بعدها
    expect(fn () => nationalIdExports($this->competition)['results']($this->director, true, 'wrong'))->toThrow(ValidationException::class, __('auth.password'))
        ->and(fn () => nationalIdExports($this->competition)['results']($this->director, true, 'password'))->toThrow(ValidationException::class)
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(0);
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
        ->assertFileDownloaded('results-students.xlsx');
    Livewire::test(CumulativeRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export_cumulative_full')->table(), ['password' => 'password'])
        ->assertFileDownloaded('cumulative-students.xlsx');

    expect(Activity::query()->where('event', 'competition_results_exported')->pluck('properties')->pluck('full_identity')->all())->toBe([true, true]);
});
