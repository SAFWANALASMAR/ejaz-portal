<?php

use App\Actions\Competitions\RunDraw;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Competitions\RelationManagers\DrawsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\CompetitionSubmission;
use App\Support\DrawSelector;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->competition = Competition::factory()->closed()->create();
    $this->manager = userWithRole(RoleName::ProgramManager);
    $this->entries = CompetitionSubmission::factory()->submitted()->count(6)->create(['competition_id' => $this->competition->id]);
    CompetitionSubmission::factory()->create(['competition_id' => $this->competition->id]);
});

it('draws winners from submitted entries only, and records everything', function () {
    $draw = app(RunDraw::class)->handle($this->manager, $this->competition, 2);
    $references = $this->entries->pluck('reference')->sort()->values()->all();

    expect($draw->entrants)->toBe($references)
        ->and($draw->entrants_count)->toBe(6)
        ->and($draw->entrants_hash)->toBe(hash('sha256', implode("\n", $references)))
        ->and($draw->seed)->toMatch('/^[0-9a-f]{64}$/')
        ->and($draw->winners)->toHaveCount(2)
        ->and(array_diff($draw->winners, $references))->toBe([])
        ->and(array_unique($draw->winners))->toHaveCount(2)
        ->and($draw->executed_by)->toBe($this->manager->id)
        ->and(Activity::where('event', 'draw_executed')->exists())->toBeTrue();
});

it('reproduces the same winners from the same seed, so the committee can verify it', function () {
    $draw = app(RunDraw::class)->handle($this->manager, $this->competition, 3);

    expect(DrawSelector::winners($draw->seed, $draw->entrants, 3))->toBe($draw->winners);

    $this->artisan('ejaz:verify-draw', ['uuid' => $draw->uuid])->assertSuccessful()->expectsOutputToContain('مطابقون');
});

it('selects every entrant evenly over many seeds', function () {
    $entrants = ['A', 'B', 'C', 'D'];
    $counts = array_fill_keys($entrants, 0);

    for ($i = 0; $i < 4000; $i++) {
        $counts[DrawSelector::winners(hash('sha256', (string) $i), $entrants, 1)[0]]++;
    }

    // التوقع 1000 لكل واحد، والانحراف الأقصى المقبول واسع لاختبار ثابت
    foreach ($counts as $count) {
        expect($count)->toBeGreaterThan(880)->toBeLessThan(1120);
    }
});

it('keeps the draw record immutable in the model and in the database', function () {
    $draw = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    expect(fn () => $draw->forceFill(['winners_count' => 2])->save())->toThrow(LogicException::class)
        ->and(fn () => $draw->delete())->toThrow(LogicException::class);

    expect(fn () => DB::transaction(fn () => DB::table('competition_draws')->where('id', $draw->id)->update(['winners_count' => 5])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('competition_draws')->where('id', $draw->id)->delete()))->toThrow(QueryException::class);

    expect(userWithRole(RoleName::SuperAdmin)->can('update', $draw))->toBeFalse()
        ->and(userWithRole(RoleName::SuperAdmin)->can('delete', $draw))->toBeFalse();
});

it('refuses to draw before closing, beyond the entrants, or without the permission', function () {
    $open = Competition::factory()->create();

    expect(fn () => app(RunDraw::class)->handle($this->manager, $open, 1))->toThrow(ValidationException::class, __('competitions.draws.still_open'))
        ->and(fn () => app(RunDraw::class)->handle($this->manager, $this->competition, 7))->toThrow(ValidationException::class)
        ->and(fn () => app(RunDraw::class)->handle($this->manager, $this->competition, 0))->toThrow(ValidationException::class);

    foreach ([RoleName::BoardMember, RoleName::Reviewer, RoleName::ContentEditor, RoleName::Member] as $role) {
        expect(fn () => app(RunDraw::class)->handle(userWithRole($role), $this->competition, 1))->toThrow(AuthorizationException::class);
    }

    expect(CompetitionDraw::count())->toBe(0);
});

it('runs the draw from the panel, and shows the record to the board without the run button', function () {
    $this->actingAs($this->manager);

    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->callAction(TestAction::make('draw')->table(), ['winners_count' => 2])
        ->assertNotified();

    expect(CompetitionDraw::sole()->winners)->toHaveCount(2);

    $board = userWithRole(RoleName::BoardMember);
    $this->actingAs($board)->get(CompetitionResource::getUrl('view', ['record' => $this->competition]))->assertOk();
    expect(DrawsRelationManager::canViewForRecord($this->competition, ViewCompetition::class))->toBeTrue();

    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('draw')->table());

    $this->actingAs(userWithRole(RoleName::Reviewer));
    expect(DrawsRelationManager::canViewForRecord($this->competition, ViewCompetition::class))->toBeFalse();
});
