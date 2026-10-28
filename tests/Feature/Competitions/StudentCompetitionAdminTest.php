<?php

use App\Actions\Competitions\CorrectStudentRegistration;
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
use App\Actions\Competitions\VoidTieDraw;
use App\Actions\Users\ChangeNationalIdExport;
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
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\StudentQuestionsSheet;
use App\Support\StudentResultsSheet;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
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
    // البنوك في الاختبارات صغيرة، فيُخفض أدنى عدد للأسئلة (30 بقرار المدير) في حفظ المرحلة، واختبار الحدين بقيمهما
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);

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

it('shows the full national ID only to holders of the full export, not to the competition creator by default, logging every view', function () {
    $student = registeredStudent($this->competition, 4);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();

    // منشئ المسابقة بلا الخيار يُرفض ويرى الرقم مقنّعاً (قرار صفوان، 27 أكتوبر 2026)
    expect(fn () => app(RevealStudentIdentity::class)->handle($this->manager, $submission))->toThrow(AuthorizationException::class);

    $this->actingAs($this->manager);
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertOk()
        ->assertSee(CompetitionSubmission::mask(studentNationalId(4)))
        ->assertDontSee(studentNationalId(4))
        ->assertActionHidden(TestAction::make('reveal')->table($submission));

    // وبالخيار من مدير النظام يكشف، كالمدير التنفيذي
    app(ChangeNationalIdExport::class)->handle($this->director, $this->manager, true);
    $this->manager->refresh();

    $reveal = function (User $user) use ($submission): ?string {
        app(RevealStudentIdentity::class)->confirm($user, $this->competition, 'password');

        return app(RevealStudentIdentity::class)->handle($user, $submission)['national_id'];
    };

    expect($reveal($this->director))->toBe(studentNationalId(4))
        ->and($reveal($this->manager))->toBe(studentNationalId(4))
        ->and(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'national_id_viewed')->get()->pluck('properties')->flatten()->implode(' '))->not->toContain(studentNationalId(4));

    // ولا لمن يملك الخيار في مسابقة لا يدير نتائجها، ولا للمشرف العام وعضو المجلس
    $other = userWithRole(RoleName::ProgramManager);
    app(ChangeNationalIdExport::class)->handle($this->director, $other, true);

    foreach ([$other->fresh(), userWithRole(RoleName::ProgramManager), userWithRole(RoleName::SuperAdmin), userWithRole(RoleName::BoardMember)] as $user) {
        expect(fn () => app(RevealStudentIdentity::class)->handle($user, $submission))->toThrow(AuthorizationException::class);
    }

    $this->actingAs(userWithRole(RoleName::ProgramManager));
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertOk()
        ->assertSee(CompetitionSubmission::mask(studentNationalId(4)))
        ->assertDontSee(studentNationalId(4))
        ->assertActionHidden(TestAction::make('reveal')->table($submission));

    // فتح نافذة الاطلاع وحده يقرأ الرقم ويسجّل الاطلاع ما دامت كلمة المرور مؤكدة في الجلسة، والجدول لا يحمله
    $this->actingAs($this->director);
    app(RevealStudentIdentity::class)->confirm($this->director, $this->competition, 'password');
    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertDontSee(studentNationalId(4))
        ->assertActionVisible(TestAction::make('reveal')->table($submission))
        ->mountAction(TestAction::make('reveal')->table($submission));

    expect(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(3);
});

it('exports results with the national ID masked by default, and in full only on the full export', function () {
    studentQuestion($this->round);
    submittedWithScore(6, 4);
    $query = fn () => $this->competition->attempts()->getQuery();

    $content = fn (User $user, bool $full = false): string => TestResponse::fromBaseResponse(StudentResultsSheet::download($user, $this->competition, $query(), $full, 'password'))->streamedContent();
    // من يدير النتائج بلا الاطلاع الكامل على الهوية (صلاحية مفردة)، ومسؤول برامج ليس منشئها لا يصدّر أصلاً. والمدير
    // التنفيذي مقنّع في التصدير العادي أيضاً (ملاحظة الاختبار الشامل 10)، وكامل في التصدير الكامل بكلمة المرور
    $resultsOnly = User::factory()->create();
    $resultsOnly->givePermissionTo('competition.manage_results');
    $masked = readXlsx($content($resultsOnly));
    $directorDefault = readXlsx($content($this->director));
    $full = readXlsx($content($this->director, true));

    expect(fn () => StudentResultsSheet::download(userWithRole(RoleName::ProgramManager), $this->competition, $query()))->toThrow(AuthorizationException::class)
        ->and($masked)->toHaveCount(2)
        ->and(implode(' ', $masked[1]))->not->toContain(studentNationalId(6))->toContain(substr(studentNationalId(6), -4))
        ->and(implode(' ', $directorDefault[1]))->not->toContain(studentNationalId(6))
        ->and(implode(' ', $full[1]))->toContain(studentNationalId(6))
        ->and(Activity::query()->where('event', 'competition_results_exported')->count())->toBe(3);
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

it('creates four rounds with the director limits, and keeps a round count and duration within them', function () {
    config(['competitions.student.min_questions' => 30]);

    $competition = app(SaveCompetition::class)->handle($this->director, new Competition, [
        'title' => 'مسابقة بأربع مراحل', 'slug' => 'four-rounds', 'opens_at' => now()->toDateTimeString(), 'closes_at' => now()->addWeek()->toDateTimeString(),
        'student_mode' => true, 'is_published' => false,
    ]);
    $rounds = $competition->rounds()->get();

    expect($rounds)->toHaveCount(4)
        ->and($rounds->pluck('name')->all())->toBe(['المرحلة الأولى', 'المرحلة الثانية', 'المرحلة الثالثة', 'المرحلة الرابعة'])
        ->and($rounds->pluck('question_count')->unique()->values()->all())->toBe([40])
        ->and($rounds->pluck('duration_minutes')->unique()->values()->all())->toBe([45]);

    $data = ['name' => 'م', 'starts_at' => now()->addMonth(), 'ends_at' => now()->addMonth()->addDay()];
    expect(fn () => app(SaveCompetitionRound::class)->handle($this->director, $competition, null, [...$data, 'question_count' => 29, 'duration_minutes' => 45]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveCompetitionRound::class)->handle($this->director, $competition, null, [...$data, 'question_count' => 41, 'duration_minutes' => 45]))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveCompetitionRound::class)->handle($this->director, $competition, null, [...$data, 'question_count' => 40, 'duration_minutes' => 46]))->toThrow(ValidationException::class)
        ->and(app(SaveCompetitionRound::class)->handle($this->director, $competition, null, [...$data, 'question_count' => 30, 'duration_minutes' => 30])->exists)->toBeTrue();
});

it('keeps results operations to the executive director and the competition creator, not every program manager', function () {
    studentQuestion($this->round);
    $attempt = submittedWithScore(1, 5);
    $this->travel(4)->hours();
    $other = userWithRole(RoleName::ProgramManager);

    foreach ([
        fn () => app(PublishRoundResults::class)->handle($other, $this->round),
        fn () => app(QualifyAttempts::class)->handle($other, $this->round, [$attempt->id]),
        fn () => app(RunTieDraw::class)->handle($other, $this->round, null, 1),
        fn () => app(IssueCompetitionCertificates::class)->handle($other, $this->competition),
    ] as $operation) {
        expect($operation)->toThrow(AuthorizationException::class);
    }

    $this->actingAs($other);
    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertActionHidden(TestAction::make('export')->table())
        ->assertActionHidden(TestAction::make('certificates')->table());

    expect(app(PublishRoundResults::class)->handle($this->director, $this->round)->resultsPublished())->toBeTrue();
});

it('voids a tie draw once with a recorded reason and allows a new draw, but not a second void', function () {
    studentQuestion($this->round);
    collect([1, 2, 3])->each(fn (int $serial) => submittedWithScore($serial, 7));
    $this->travel(4)->hours();
    $this->round->forceFill(['results_published_at' => now()])->save();
    [$first] = app(RunTieDraw::class)->handle($this->director, $this->round, null, 2);

    expect(fn () => app(VoidTieDraw::class)->handle($this->director, $first, 'قصير'))->toThrow(ValidationException::class);

    app(VoidTieDraw::class)->handle($this->director, $first, 'أُدخل عدد المراكز خطأً في اللجنة');
    [$second, $fresh] = app(RunTieDraw::class)->handle($this->director, $this->round, null, 1);

    expect($fresh)->toBeTrue()->and($second->id)->not->toBe($first->id)
        ->and(Activity::query()->where('event', 'draw_voided')->first()?->properties['reason'])->toBe('أُدخل عدد المراكز خطأً في اللجنة')
        ->and(fn () => app(VoidTieDraw::class)->handle($this->director, $second, 'سبب ثانٍ مكتوب بطوله'))->toThrow(ValidationException::class)
        ->and(fn () => app(VoidTieDraw::class)->handle(userWithRole(RoleName::SuperAdmin), $second, 'سبب ثالث مكتوب بطوله'))->toThrow(AuthorizationException::class);
});

it('waits for the network grace after the round end before publishing', function () {
    studentQuestion($this->round);
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    $this->travelTo($this->round->ends_at->copy()->addSeconds(5));

    expect(fn () => app(PublishRoundResults::class)->handle($this->director, $this->round))->toThrow(ValidationException::class);

    $this->travel(30)->seconds();

    expect(app(PublishRoundResults::class)->handle($this->director, $this->round)->resultsPublished())->toBeTrue();
});

it('extends the deadline of running attempts when the round end is extended', function () {
    studentQuestion($this->round);
    $this->round->forceFill(['ends_at' => now()->addMinutes(10)])->save();
    $attempt = app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    expect($attempt->deadline_at->timestamp)->toBe($this->round->ends_at->timestamp);

    $round = $this->round->fresh();
    app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, [
        'name' => $round->name, 'starts_at' => $round->starts_at, 'ends_at' => $round->ends_at->copy()->addHours(2),
        'question_count' => $round->question_count, 'duration_minutes' => 30,
    ]);

    expect($attempt->fresh()->deadline_at->timestamp)->toBe($attempt->started_at->copy()->addMinutes(30)->timestamp);
});

it('asks for the password in the reveal window before the first reveal, then clears the identity when it closes', function () {
    $submission = CompetitionSubmission::query()->where('user_id', registeredStudent($this->competition, 4)->id)->sole();
    $this->actingAs($this->director);

    Livewire::test(RegistrationsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->mountAction(TestAction::make('reveal')->table($submission))
        ->assertSet('revealed', null)
        ->assertDontSee(studentNationalId(4))
        ->fillForm(['password' => 'wrong-password'])
        ->callMountedAction()
        ->assertSet('revealed', null)
        ->fillForm(['password' => 'password'])
        ->callMountedAction()
        ->assertSet('revealed.national_id', studentNationalId(4))
        ->assertSet('mountedActions.0.data.password', null)
        ->call('unmountAction')
        ->assertSet('revealed', null);

    expect(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(1);
});

it('reveals a national ID only after the password, for a short while, and up to an hourly limit', function () {
    $submission = CompetitionSubmission::query()->where('user_id', registeredStudent($this->competition, 4)->id)->sole();
    $reveal = fn () => app(RevealStudentIdentity::class)->handle($this->director, $submission);

    // بلا تأكيد، وبكلمة مرور خاطئة: لا عرض ولا سجل اطلاع، والخاطئة تُحسب على قفل تأكيد كلمة المرور
    expect($reveal)->toThrow(ValidationException::class)
        ->and(fn () => app(RevealStudentIdentity::class)->confirm($this->director, $this->competition, 'wrong-password'))->toThrow(ValidationException::class)
        ->and(RateLimiter::attempts('confirm-password:'.$this->director->id))->toBe(1)
        ->and(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(0);

    // والتأكيد لصاحبه وحده، فلا يكشف به غيره في الجلسة نفسها
    app(RevealStudentIdentity::class)->confirm($this->director, $this->competition, 'password');
    app(ChangeNationalIdExport::class)->handle($this->director, $this->manager, true);

    expect($reveal()['national_id'])->toBe(studentNationalId(4))
        ->and(fn () => app(RevealStudentIdentity::class)->handle($this->manager->fresh(), $submission))->toThrow(ValidationException::class);

    // ينتهي التأكيد بعد مدته
    $this->travel(RevealStudentIdentity::CONFIRM_SECONDS + 1)->seconds();
    expect($reveal)->toThrow(ValidationException::class);

    // وحد الساعة لكل مستخدم
    config(['security.national_id_reveals_per_hour' => 2]);
    app(RevealStudentIdentity::class)->confirm($this->director, $this->competition, 'password');
    $reveal();

    expect($reveal)->toThrow(ValidationException::class)
        ->and(Activity::query()->where('event', 'national_id_viewed')->count())->toBe(2);
});

it('refuses an import whose uncompressed size is too large, and restores cells protected from formulas', function () {
    $bomb = tempnam(sys_get_temp_dir(), 'z').'.xlsx';
    $zip = new ZipArchive;
    $zip->open($bomb, ZipArchive::CREATE);
    $zip->addFromString('xl/worksheets/sheet1.xml', str_repeat(' ', 21 * 1024 * 1024));
    $zip->close();

    expect(fn () => app(ImportStudentQuestions::class)->handle($this->manager, $this->round, $bomb))->toThrow(ValidationException::class);

    $file = questionsXlsx([['صح وخطأ', '', '', "'=1+1 عبارة تجريبية", '', '', '', '', '', '', 'صح', '1']]);
    app(ImportStudentQuestions::class)->handle($this->manager, $this->round, $file);

    expect($this->round->questions()->sole()->prompt)->toBe('=1+1 عبارة تجريبية');
});

it('lets the executive director correct or cancel a registration with a logged reason, freeing a squatted national ID', function () {
    $squatter = registeredStudent($this->competition, 9);
    $submission = CompetitionSubmission::query()->where('user_id', $squatter->id)->sole();
    $correct = app(CorrectStudentRegistration::class);

    expect(fn () => $correct->cancel($this->manager, $submission, 'سبب مكتوب بطوله'))->toThrow(AuthorizationException::class)
        ->and(fn () => $correct->cancel(userWithRole(RoleName::SuperAdmin), $submission, 'سبب مكتوب بطوله'))->toThrow(AuthorizationException::class);

    $correct->edit($this->director, $submission, ['full_name' => 'اسم مصحح بن مثال تجريبي', 'category_id' => $submission->category_id, 'grade' => 'الثالث الثانوي', 'region_id' => $submission->region_id, 'gender' => 'male', 'reason' => 'خطأ إملائي في الاسم من المدرسة']);
    expect($submission->fresh()->full_name)->toBe('اسم مصحح بن مثال تجريبي');

    $correct->cancel($this->director, $submission->fresh(), 'رقم هوية طالب آخر سُجّل بغير حق');
    $owner = registeredStudent($this->competition, 9);

    expect(CompetitionSubmission::query()->whereKey($submission->id)->exists())->toBeFalse()
        ->and(CompetitionSubmission::query()->withTrashed()->whereKey($submission->id)->value('national_id_hash'))->toBeNull()
        ->and(CompetitionSubmission::query()->where('user_id', $owner->id)->exists())->toBeTrue()
        ->and(Activity::query()->where('event', 'registration_corrected')->count())->toBe(2)
        ->and(Activity::query()->where('event', 'registration_corrected')->get()->pluck('properties')->flatten()->implode(' '))->not->toContain(studentNationalId(9));
});

it('shows the live counts: registered, taking the exam now, and submitted', function () {
    studentQuestion($this->round);
    app(StartAttempt::class)->handle(registeredStudent($this->competition, 1), $this->round);
    submittedWithScore(2, 1);
    registeredStudent($this->competition, 3);
    $this->actingAs($this->director);

    Livewire::test(RoundsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertSee(__('competitions.student.admin.registered_count', ['count' => 3]))
        ->assertSee(__('competitions.student.admin.running'))
        ->assertTableColumnStateSet('running_count', 1, $this->round)
        ->assertTableColumnStateSet('submitted_count', 1, $this->round);
});

it('rejects a certificate background path by its pattern alone', function () {
    try {
        app(SaveCompetition::class)->handle($this->manager, $this->competition, [
            'title' => $this->competition->title, 'slug' => 'students', 'opens_at' => $this->competition->opens_at, 'closes_at' => $this->competition->closes_at,
            'certificate_background' => 'competition-certificate-templates/x..png',
        ]);
        $this->fail('expected a validation error');
    } catch (ValidationException $exception) {
        expect($exception->errors()['certificate_background'][0])->toContain(__('competitions.attributes.certificate_background'));
    }
});

it('keeps the category of a registration once the student has started an attempt', function () {
    studentQuestion($this->round);
    $student = registeredStudent($this->competition, 11);
    $submission = CompetitionSubmission::query()->where('user_id', $student->id)->sole();
    $other = $this->competition->openCategories()->whereKeyNot($submission->category_id)->firstOrFail();
    $correct = app(CorrectStudentRegistration::class);
    $data = fn (array $overrides = []): array => ['full_name' => $submission->full_name, 'category_id' => $submission->category_id, 'grade' => $submission->grade, 'region_id' => $submission->region_id, 'gender' => 'male', 'reason' => 'تصحيح من المدرسة بعد المراجعة', ...$overrides];

    app(StartAttempt::class)->handle($student, $this->round);

    expect(fn () => $correct->edit($this->director, $submission, $data(['category_id' => $other->id, 'grade' => $other->gradeList()[0] ?? null])))->toThrow(ValidationException::class)
        ->and($submission->fresh()->category_id)->toBe($submission->category_id)
        ->and($correct->edit($this->director, $submission, $data(['full_name' => 'اسم مصحح بن مثال تجريبي']))->full_name)->toBe('اسم مصحح بن مثال تجريبي');
});

it('re-checks the round end on the locked row, refusing to publish from a copy read before the end was extended', function () {
    studentQuestion($this->round);
    $attempt = app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    $this->travel(4)->hours();
    $stale = $this->round->fresh();

    // مدّ النهاية بعد أن قرأ النشرُ المرحلة: النشر يرى النهاية الجديدة على الصف المقفول، فلا يسلّم ولا ينشر
    app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $this->round->fresh(), [
        'name' => $stale->name, 'starts_at' => $stale->starts_at, 'ends_at' => now()->addHour(),
        'question_count' => $stale->question_count, 'duration_minutes' => $stale->duration_minutes,
    ]);

    expect(fn () => app(PublishRoundResults::class)->handle($this->director, $stale))->toThrow(ValidationException::class)
        ->and($this->round->fresh()->resultsPublished())->toBeFalse()
        ->and($attempt->fresh()->isInProgress())->toBeTrue();
});

it('creates the default rounds when the competition is created, not again after they are all deleted', function () {
    $data = ['title' => 'مسابقة بلا مراحل', 'slug' => 'no-rounds', 'opens_at' => now()->toDateTimeString(), 'closes_at' => now()->addWeek()->toDateTimeString(), 'student_mode' => true, 'is_published' => false];
    $competition = app(SaveCompetition::class)->handle($this->director, new Competition, $data);

    expect($competition->rounds()->count())->toBe(4);

    $competition->rounds()->with('competition')->get()->each(fn ($round) => app(DeleteStudentCompetitionItem::class)->handle($this->director, $round));
    app(SaveCompetition::class)->handle($this->director, $competition->fresh(), $data);

    expect($competition->rounds()->count())->toBe(0);
});

it('keeps a round start stored with seconds when the panel saves it to the minute', function () {
    studentQuestion($this->round);
    $this->round->forceFill(['starts_at' => $this->round->starts_at->copy()->startOfMinute()->addSeconds(37), 'ends_at' => $this->round->ends_at->copy()->startOfMinute()->addSeconds(12)])->save();
    app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round->fresh());
    $round = $this->round->fresh();

    $saved = app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, [
        'name' => 'اسم آخر', 'starts_at' => $round->starts_at->format('Y-m-d H:i'), 'ends_at' => $round->ends_at->format('Y-m-d H:i'),
        'question_count' => $round->question_count, 'duration_minutes' => $round->duration_minutes,
    ]);

    expect($saved->name)->toBe('اسم آخر')
        ->and($saved->fresh()->starts_at->equalTo($round->starts_at))->toBeTrue()
        ->and($saved->fresh()->ends_at->equalTo($round->ends_at))->toBeTrue()
        ->and(fn () => app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $saved->fresh(), [
            'name' => 'اسم آخر', 'starts_at' => $round->starts_at->copy()->addMinute()->format('Y-m-d H:i'), 'ends_at' => $round->ends_at->format('Y-m-d H:i'),
            'question_count' => $round->question_count, 'duration_minutes' => $round->duration_minutes,
        ]))->toThrow(ValidationException::class);

    // ومرحلة جديدة تُحفظ بالدقيقة
    $new = app(SaveCompetitionRound::class)->handle($this->director, $this->competition, null, [
        'name' => 'مرحلة', 'starts_at' => now()->addWeek()->setSeconds(45), 'ends_at' => now()->addWeek()->addDay()->setSeconds(10), 'question_count' => 1, 'duration_minutes' => 30,
    ]);
    expect($new->fresh()->starts_at->second)->toBe(0)->and($new->fresh()->ends_at->second)->toBe(0);
});

it('ranks exported results within each round and category, by score whatever the table order', function () {
    studentQuestion($this->round);
    $low = submittedWithScore(1, 3);
    submittedWithScore(2, 9);
    $second = new CompetitionRound(['name' => 'الثانية', 'sort_order' => 2, 'starts_at' => now()->subMinute(), 'ends_at' => now()->addHours(3), 'question_count' => 1, 'duration_minutes' => 30]);
    $second->competition_id = $this->competition->id;
    $second->save();
    studentQuestion($second);
    $student = $low->submission->user;
    $attempt = app(StartAttempt::class)->handle($student, $second);
    app(SubmitAttempt::class)->handle($student, $attempt);
    $attempt->forceFill(['score' => 5])->save();

    $rows = readXlsx(TestResponse::fromBaseResponse(StudentResultsSheet::download($this->director, $this->competition, $this->competition->attempts()->getQuery()->orderBy('competition_attempts.id')))->streamedContent());
    $round = array_search(__('competitions.student.question_attributes.round_id'), $rows[0], true);
    $score = array_search(__('competitions.student.admin.score'), $rows[0], true);
    $ranked = collect(array_slice($rows, 1))->map(fn (array $row) => [$row[$round], $row[0], $row[$score]])->all();

    expect($ranked)->toBe([
        [$this->round->name, '1', '9'],
        [$second->name, '1', '5'],
        [$this->round->name, '2', '3'],
    ]);
});

it('rolls the draws index back to unique only when no voided draw was redrawn', function () {
    $migration = require database_path('migrations/2026_10_26_100000_adjust_student_competition_attempts_and_draws.php');
    $indexes = fn (): array => collect(DB::select("select indexname from pg_indexes where tablename = 'competition_draws'"))->pluck('indexname')->all();

    // بلا سحب مكرر: يعود الفهرس الفريد، ثم يُعاد الترحيل
    $migration->down();
    expect($indexes())->toContain('competition_draws_round_category_unique');
    $migration->up();
    expect($indexes())->toContain('competition_draws_round_category_index')->not->toContain('competition_draws_round_category_unique');

    // وبسحب ملغى وإعادته: يتوقف التراجع قبل أي تغيير (سجل السحب لا يُحذف)
    studentQuestion($this->round);
    collect([1, 2, 3])->each(fn (int $serial) => submittedWithScore($serial, 7));
    $this->travel(4)->hours();
    $this->round->forceFill(['results_published_at' => now()])->save();
    [$first] = app(RunTieDraw::class)->handle($this->director, $this->round, null, 2);
    app(VoidTieDraw::class)->handle($this->director, $first, 'أُدخل عدد المراكز خطأً في اللجنة');
    app(RunTieDraw::class)->handle($this->director, $this->round, null, 1);

    expect(fn () => $migration->down())->toThrow(RuntimeException::class)
        ->and($indexes())->toContain('competition_draws_round_category_index')->not->toContain('competition_draws_round_category_unique')
        ->and(Schema::hasColumn('competition_attempts', 'question_count'))->toBeTrue();
});

it('leaves running attempts untouched when a round is saved without changing its end (cloud review, high 1)', function () {
    studentQuestion($this->round);
    $attempt = app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    // موعد مختلف عمّا يُحسب من البدء والمدة والنهاية، فأي إعادة حساب تغيّره
    $attempt->forceFill(['deadline_at' => now()->addMinutes(7)->startOfSecond()])->save();
    $deadline = $attempt->fresh()->deadline_at->timestamp;

    $round = $this->round->fresh();
    app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, [
        'name' => 'تصحيح حرف في الاسم', 'starts_at' => $round->starts_at, 'ends_at' => $round->ends_at,
        'question_count' => $round->question_count, 'duration_minutes' => 30,
    ]);

    expect($attempt->fresh()->deadline_at->timestamp)->toBe($deadline);
});

it('keeps a cancelled registration out of the results table, their export and qualification (cloud review, medium 4)', function () {
    studentQuestion($this->round);
    $kept = submittedWithScore(1, 3);
    $cancelled = submittedWithScore(2, 9);
    app(CorrectStudentRegistration::class)->cancel($this->director, $cancelled->submission, 'انتحال اكتُشف بعد التسليم');
    $this->round->forceFill(['results_published_at' => now()])->save();
    $this->actingAs($this->director);

    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertCanSeeTableRecords([$kept])
        ->assertCanNotSeeTableRecords([$cancelled]);

    $rows = readXlsx(TestResponse::fromBaseResponse(StudentResultsSheet::download($this->director, $this->competition, $this->competition->attempts()->getQuery()))->streamedContent());
    $score = array_search(__('competitions.student.admin.score'), $rows[0], true);

    expect($rows)->toHaveCount(2)
        ->and([$rows[1][0], $rows[1][$score]])->toBe(['1', '3'])
        ->and(app(QualifyAttempts::class)->handle($this->director, $this->round, [$kept->id, $cancelled->id]))->toBe(1)
        ->and($cancelled->fresh()->qualified_at)->toBeNull();
});

it('leaves running attempts untouched when a round is saved during its window without extending its end', function () {
    studentQuestion($this->round);
    $attempt = app(StartAttempt::class)->handle(registeredStudent($this->competition), $this->round);
    $round = $this->round->fresh();
    $writes = [];
    DB::listen(function ($query) use (&$writes): void {
        if (preg_match('/^update "competition_attempts"|competition_attempts.+for update/is', $query->sql)) {
            $writes[] = $query->sql;
        }
    });

    // تصحيح حرف في الاسم والفرع أثناء النافذة (المراجعة السحابية، ع١)
    app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, [
        'name' => 'المرحلة الأولى مصححة', 'branch' => 'فرع مصحح', 'starts_at' => $round->starts_at, 'ends_at' => $round->ends_at,
        'question_count' => $round->question_count, 'duration_minutes' => $round->duration_minutes, 'qualified_only' => $round->qualified_only,
    ]);

    expect($writes)->toBe([])
        ->and($round->fresh()->name)->toBe('المرحلة الأولى مصححة')
        ->and($attempt->fresh()->deadline_at->timestamp)->toBe($attempt->deadline_at->timestamp);
});

it('extends, in one statement, only the running attempts the old end cut short', function () {
    studentQuestion($this->round);
    $this->round->forceFill(['ends_at' => now()->addMinutes(10), 'duration_minutes' => 30])->save();
    $cut = app(StartAttempt::class)->handle(registeredStudent($this->competition, 1), $this->round);
    $early = app(StartAttempt::class)->handle(registeredStudent($this->competition, 2), $this->round);
    DB::table('competition_attempts')->where('id', $early->id)->update([
        'started_at' => now()->subMinutes(25), 'deadline_at' => now()->addMinutes(5), 'updated_at' => '2026-01-01 00:00:00',
    ]);
    $submitted = submittedWithScore(3, 4);
    $submitted->forceFill(['deadline_at' => $this->round->ends_at])->save();
    $round = $this->round->fresh();
    $updates = [];
    DB::listen(function ($query) use (&$updates): void {
        if (str_starts_with(strtolower($query->sql), 'update competition_attempts')) {
            $updates[] = $query->sql;
        }
    });

    app(SaveCompetitionRound::class)->handle($this->director, $this->competition, $round, [
        'name' => $round->name, 'starts_at' => $round->starts_at, 'ends_at' => $round->ends_at->copy()->addHours(2),
        'question_count' => $round->question_count, 'duration_minutes' => 30, 'qualified_only' => $round->qualified_only,
    ]);

    expect($updates)->toHaveCount(1)
        ->and($cut->fresh()->deadline_at->timestamp)->toBe($cut->started_at->copy()->addMinutes(30)->timestamp)
        ->and($early->fresh()->updated_at->year)->toBe(2026)
        ->and($early->fresh()->updated_at->month)->toBe(1)
        ->and($submitted->fresh()->deadline_at->timestamp)->toBe($round->ends_at->timestamp);
});

it('exports only submitted attempts of standing registrations, leaving running and cancelled ones out', function () {
    studentQuestion($this->round);
    $cancelled = submittedWithScore(1, 9);
    $kept = submittedWithScore(2, 3);
    $running = app(StartAttempt::class)->handle(registeredStudent($this->competition, 3), $this->round);
    // إلغاء تسجيل سلّم صاحبه بأعلى درجة، لانتحال اكتُشف بعد التسليم (المراجعة السحابية، م٤)
    app(CorrectStudentRegistration::class)->cancel($this->director, $cancelled->submission, 'انتحال اكتُشف بعد التسليم');
    $this->round->forceFill(['results_published_at' => now()])->save();
    $this->actingAs($this->director);

    Livewire::test(ResultsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertCanSeeTableRecords([$kept, $running])
        ->assertCanNotSeeTableRecords([$cancelled]);

    // والتصدير يرتّب المسلَّمة لتسجيل قائم وحدها: لا الملغى بدرجته، ولا الجارية بصفر
    $rows = readXlsx(TestResponse::fromBaseResponse(StudentResultsSheet::download($this->director, $this->competition, $this->competition->attempts()->getQuery()))->streamedContent());

    expect(array_map(fn (array $row): array => [$row[0], $row[1]], array_slice($rows, 1)))->toBe([['1', (string) $kept->submission->reference]])
        ->and(app(QualifyAttempts::class)->handle($this->director, $this->round, [$cancelled->id, $kept->id]))->toBe(1)
        ->and($cancelled->fresh()->qualified_at)->toBeNull()
        ->and($kept->fresh()->qualified_at)->not->toBeNull();
});
