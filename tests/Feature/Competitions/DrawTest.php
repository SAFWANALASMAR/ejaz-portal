<?php

use App\Actions\Competitions\RunDraw;
use App\Actions\Competitions\SaveCompetition;
use App\Actions\Competitions\VoidDraw;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Pages\EditCompetition;
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

it('allows one active draw per competition, so it cannot be repeated until a wanted result', function () {
    app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    expect(fn () => app(RunDraw::class)->handle($this->manager, $this->competition, 1))->toThrow(ValidationException::class, __('competitions.draws.already_drawn'))
        ->and(CompetitionDraw::count())->toBe(1);
});

it('lets only the super admin void a draw with a reason, keeps it visible, and then allows a new one', function () {
    $first = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    expect(fn () => app(VoidDraw::class)->handle($this->manager, $first, 'سبب كافٍ للإلغاء هنا'))->toThrow(AuthorizationException::class);

    $admin = userWithRole(RoleName::SuperAdmin);
    expect(fn () => app(VoidDraw::class)->handle($admin, $first, 'قصير'))->toThrow(ValidationException::class);

    app(VoidDraw::class)->handle($admin, $first, 'خطأ في قائمة المشاركات قبل السحب');
    $second = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    expect($first->fresh()->void->reason)->toBe('خطأ في قائمة المشاركات قبل السحب')
        ->and($this->competition->draws()->count())->toBe(2)
        ->and($second->void)->toBeNull()
        ->and(Activity::where('event', 'draw_voided')->exists())->toBeTrue()
        ->and(fn () => DB::transaction(fn () => DB::table('competition_draw_voids')->delete()))->toThrow(QueryException::class)
        ->and(fn () => DB::statement('truncate competition_draws cascade'))->toThrow(QueryException::class);
});

it('refuses a draw on a deleted or unpublished competition, and leaves out deleted entries', function () {
    $this->entries->first()->delete();
    $draw = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    expect($draw->entrants_count)->toBe(5);

    $deleted = Competition::factory()->closed()->create();
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $deleted->id]);
    $deleted->delete();

    expect(fn () => app(RunDraw::class)->handle($this->manager, $deleted, 1))->toThrow(AuthorizationException::class);

    $this->artisan('ejaz:verify-draw', ['uuid' => $draw->uuid])->assertSuccessful();
    $this->competition->delete();
    $this->artisan('ejaz:verify-draw', ['uuid' => $draw->uuid])->assertSuccessful();
});

it('locks the schedule and publishing after a draw', function () {
    app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    $base = ['title' => 'x', 'slug' => $this->competition->slug, 'opens_at' => $this->competition->opens_at->toDateTimeString(), 'closes_at' => now()->addWeek()->toDateTimeString(), 'is_published' => true];

    expect(fn () => app(SaveCompetition::class)->handle($this->manager, $this->competition, $base))
        ->toThrow(ValidationException::class, __('competitions.validation.schedule_locked'));

    // المسابقة هنا بلا أسئلة، فلا يُرسل النشر (غير المتغير) حتى لا يُفحص شرط الأسئلة
    $unchanged = collect($base)->except('is_published')->all();
    app(SaveCompetition::class)->handle($this->manager, $this->competition, [...$unchanged, 'closes_at' => $this->competition->closes_at->toDateTimeString(), 'title' => 'عنوان جديد']);
    expect($this->competition->fresh()->title)->toBe('عنوان جديد');
});

it('hides the correct option from the board on the read-only page', function () {
    $competition = Competition::factory()->withQuestions()->create();
    $this->actingAs(userWithRole(RoleName::BoardMember));

    $page = Livewire::test(ViewCompetition::class, ['record' => $competition->getRouteKey()]);
    $options = collect($page->get('data.questions'))->pluck('options')->flatten(1)->filter();

    expect($options->pluck('is_correct')->unique()->values()->all())->toBe([false]);
});

it('still saves other changes from the edit page after a draw, and refuses a new closing date', function () {
    $competition = Competition::factory()->closed()->withQuestions()->create();
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id]);
    app(RunDraw::class)->handle($this->manager, $competition, 1);
    $this->actingAs($this->manager);

    Livewire::test(EditCompetition::class, ['record' => $competition->getRouteKey()])
        ->fillForm(['title' => 'عنوان بعد السحب'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($competition->fresh()->title)->toBe('عنوان بعد السحب');

    // تجاوز التعطيل من المتصفح لا يغيّر الموعد
    Livewire::test(EditCompetition::class, ['record' => $competition->getRouteKey()])
        ->set('data.closes_at', now()->addMonth()->format('Y-m-d H:i:s'))
        ->call('save')
        ->assertHasFormErrors(['closes_at']);
});
