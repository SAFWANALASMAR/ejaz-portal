<?php

use App\Enums\RoleName;
use App\Enums\SaudiRegion;
use App\Filament\Exports\SubmissionExporter;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
use App\Filament\Resources\Competitions\RelationManagers\SubmissionsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionStats;
use Filament\Actions\Exports\Models\Export;
use Filament\Actions\Testing\TestAction;
use Livewire\Livewire;

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

    expect($export->total_rows)->toBe(6)->and($export->user_id)->toBe($manager->id);

    $other = userWithRole(RoleName::ProgramManager);
    expect($manager->can('view', $export))->toBeTrue()
        ->and($other->can('view', $export))->toBeFalse();

    $manager->removeRole(RoleName::ProgramManager->value);
    expect($manager->fresh()->can('view', $export))->toBeFalse();
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
