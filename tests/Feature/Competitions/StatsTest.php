<?php

use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Enums\RoleName;
use App\Enums\SaudiRegion;
use App\Filament\Exports\SubmissionExporter;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\SubmissionsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionCategory;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionStats;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->competition = Competition::factory()->create();
    CompetitionSubmission::factory()->submitted()->count(3)->create(['competition_id' => $this->competition->id, 'category' => 'الفئة الأولى', 'region' => SaudiRegion::Makkah]);
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $this->competition->id, 'category' => 'الفئة الثانية', 'region' => SaudiRegion::Riyadh]);
    CompetitionSubmission::factory()->count(2)->create(['competition_id' => $this->competition->id]);
    CompetitionSubmission::factory()->submitted()->create();
});

it('counts registered, drafts and submitted entries, by category and region, for this competition only', function () {
    expect(CompetitionStats::compute($this->competition))->toBe([
        'registered' => 6,
        'drafts' => 2,
        'submitted' => 4,
        'categories' => ['الفئة الأولى' => 3, 'الفئة الثانية' => 1],
        'regions' => [SaudiRegion::Makkah->getLabel() => 3, SaudiRegion::Riyadh->getLabel() => 1],
    ]);
});

it('shows the dashboard to the manager and the board without personal data, and keeps others out', function () {
    $url = CompetitionResource::getUrl('stats', ['record' => $this->competition]);
    $applicant = $this->competition->submissions()->with('user')->first()->user;

    $this->actingAs(userWithRole(RoleName::ProgramManager))->get($url)->assertOk()->assertSee('الفئة الأولى')->assertSee(SaudiRegion::Makkah->getLabel());
    $this->actingAs(userWithRole(RoleName::BoardMember))->get($url)->assertOk()->assertDontSee($applicant->email);
    $this->actingAs(userWithRole(RoleName::Reviewer))->get($url)->assertForbidden();
    $this->actingAs(userWithRole(RoleName::ContentEditor))->get($url)->assertForbidden();
});

it('exports this competition entries in the queue, downloadable only by the requester with the permission', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $this->actingAs($manager);

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export')->table(), ['columnMap' => collect(SubmissionExporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])->all()])
        ->assertNotified();

    $export = Export::sole();

    // المرسلة وحدها افتراضياً، لا بريد ولا جوال من بدأ مسودة ولم يرسلها
    expect($export->total_rows)->toBe(4)->and($export->user_id)->toBe($manager->id);

    $other = userWithRole(RoleName::ProgramManager);
    expect($manager->can('view', $export))->toBeTrue()
        ->and($other->can('view', $export))->toBeFalse();

    $manager->removeRole(RoleName::ProgramManager->value);
    expect($manager->fresh()->can('view', $export))->toBeFalse();
});

it('adds drafts to the export only on request, and leaves email and phone off by default', function () {
    $this->actingAs(userWithRole(RoleName::ProgramManager));

    $defaults = collect(SubmissionExporter::getColumns())->filter->isEnabledByDefault()->map->getName()->values()->all();
    expect($defaults)->not->toContain('user.email')->not->toContain('user.phone')->toContain('reference');

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export')->table(), [
            'include_drafts' => true,
            'columnMap' => collect(SubmissionExporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])->all(),
        ])
        ->assertNotified();

    expect(Export::sole()->total_rows)->toBe(6);

    // السجل يذكر ما صُدّر: المسودات، والأعمدة المفعّلة ومنها البريد والجوال
    $logged = Activity::where('event', 'submissions_exported')->sole()->properties;
    expect($logged['include_drafts'])->toBeTrue()
        ->and($logged['columns'])->toContain('user.email')->toContain('user.phone')->toContain('reference');
});

it('hides the export from whoever may see the entries but not export them', function () {
    $viewer = userWithRole(RoleName::BoardMember);
    $viewer->givePermissionTo('submissions.view');
    $this->actingAs($viewer);

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->assertActionHidden(TestAction::make('export')->table())
        ->assertActionHidden(TestAction::make('distribute')->table());
});

it('creates load test accounts outside production only', function () {
    $this->artisan('ejaz:load-accounts', ['count' => 3, '--json' => true])->assertSuccessful();

    expect(User::where('email', 'like', '%@load.test')->count())->toBe(3)
        ->and(User::where('email', 'load-1@load.test')->sole()->hasVerifiedPhone())->toBeTrue()
        ->and(User::where('email', 'load-2@load.test')->sole()->hasRole(RoleName::Member->value))->toBeTrue();

    app()->detectEnvironment(fn () => 'production');
    $this->artisan('ejaz:load-accounts', ['count' => 3])->assertFailed();
    app()->detectEnvironment(fn () => 'testing');
});

it('logs the export request and prunes export files after a day', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $this->actingAs($manager);

    Livewire::test(SubmissionsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => EditCompetition::class])
        ->callAction(TestAction::make('export')->table(), ['columnMap' => collect(SubmissionExporter::getColumns())->mapWithKeys(fn ($column) => [$column->getName() => ['isEnabled' => true, 'label' => $column->getLabel()]])->all()]);

    $export = Export::sole();
    $export->getFileDisk()->put($export->getFileDirectory().'/0000000000000001.csv', 'x');

    expect(Activity::where('event', 'submissions_exported')->where('causer_id', $manager->id)->exists())->toBeTrue();

    $this->artisan('ejaz:prune-exports')->assertSuccessful();
    expect(Export::count())->toBe(1);

    $this->travel(25)->hours();
    $this->artisan('ejaz:prune-exports')->assertSuccessful();

    expect(Export::count())->toBe(0)->and($export->getFileDisk()->directoryExists($export->getFileDirectory()))->toBeFalse();
});

it('escapes spreadsheet formulas in every column an applicant can type', function () {
    $protected = collect(SubmissionExporter::getColumns())->filter->shouldPreventFormulaInjection()->map->getName()->values()->all();

    expect($protected)->toContain('reference', 'user.name', 'user.email', 'user.phone', 'category');
});

it('serves the export file only to its requester with the permission, through the real route', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $export = new Export;
    $export->forceFill(['user_id' => $manager->id, 'file_disk' => 'local', 'file_name' => 'export-1', 'exporter' => SubmissionExporter::class, 'total_rows' => 1, 'completed_at' => now()])->save();
    Storage::disk('local')->put($export->getFileDirectory().'/0000000000000001.csv', 'a,b');
    Storage::disk('local')->put($export->getFileDirectory().'/headers.csv', 'h1,h2');
    $url = route('filament.exports.download', ['export' => $export, 'format' => 'csv']);

    $this->actingAs($manager)->get($url)->assertOk();
    $this->actingAs(userWithRole(RoleName::ProgramManager))->get($url)->assertForbidden();

    $manager->removeRole(RoleName::ProgramManager->value);
    $this->actingAs($manager->fresh())->get($url)->assertForbidden();
});

it('refuses the export download without two-factor authentication', function () {
    $manager = userWithRole(RoleName::ProgramManager);
    $export = new Export;
    $export->forceFill(['user_id' => $manager->id, 'file_disk' => 'local', 'exporter' => SubmissionExporter::class, 'total_rows' => 1])->save();

    expect($manager->can('view', $export))->toBeTrue();

    $manager->forceFill(['two_factor_confirmed_at' => null, 'two_factor_secret' => null])->save();
    expect($manager->fresh()->can('view', $export))->toBeFalse();
});

it('creates load accounts only with the log SMS driver', function () {
    config(['services.sms.driver' => 'unifonic']);

    $this->artisan('ejaz:load-accounts', ['count' => 2])->assertFailed();
    expect(User::where('email', 'like', '%@load.test')->count())->toBe(0);
});

it('counts the attempts of a student competition: registered, running and submitted, by category and region', function () {
    config(['competitions.exam.national_id_key' => 'test-hmac-key', 'competitions.student.min_questions' => 1]);
    $competition = Competition::factory()->student()->create(['slug' => 'students-stats']);
    $round = $competition->rounds()->firstOrFail();
    studentQuestion($round);
    $middle = CompetitionCategory::query()->where('name', 'المرحلة الجامعية')->value('id');

    // خمسة يسلّمون (أربعة من الثانوية وواحد من الجامعية)، وواحد يختبر الآن، وواحد سجّل ولم يبدأ، وواحد أُلغي تسجيله
    foreach (range(1, 5) as $serial) {
        $student = registeredStudent($competition, $serial, $serial === 5 ? ['category_id' => $middle, 'grade' => null] : []);
        $attempt = app(StartAttempt::class)->handle($student, $round);
        app(SubmitAttempt::class)->handle($student, $attempt);
    }

    app(StartAttempt::class)->handle(registeredStudent($competition, 6), $round);
    registeredStudent($competition, 7);
    $cancelled = registeredStudent($competition, 8);
    app(StartAttempt::class)->handle($cancelled, $round);
    CompetitionSubmission::query()->where('user_id', $cancelled->id)->sole()->delete();

    expect(CompetitionStats::compute($competition))->toBe([
        'registered' => 7,
        'drafts' => 1,
        'submitted' => 5,
        'categories' => ['المرحلة الثانوية' => 4, 'المرحلة الجامعية' => 1],
        'regions' => ['منطقة مكة المكرمة' => 5],
    ]);

    $this->actingAs(userWithRole(RoleName::ExecutiveDirector))
        ->get(CompetitionResource::getUrl('stats', ['record' => $competition]))
        ->assertOk()
        ->assertSeeInOrder([__('competitions.stats.student_drafts'), '1', __('competitions.stats.student_submitted'), '5'])
        ->assertDontSee(__('competitions.stats.drafts'));
});
