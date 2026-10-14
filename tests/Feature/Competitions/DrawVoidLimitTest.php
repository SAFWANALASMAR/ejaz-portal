<?php

use App\Actions\Competitions\ApproveDrawVoid;
use App\Actions\Competitions\RunDraw;
use App\Actions\Competitions\VoidDraw;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Competitions\RelationManagers\DrawsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionDrawVoid;
use App\Models\CompetitionDrawVoidRequest;
use App\Models\CompetitionSubmission;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
 * إلغاء سحب واحد لكل مسابقة بقرار مشرف عام واحد، وما بعده يحتاج اعتماد مشرف عام ثانٍ غير طالب الإلغاء،
 * فلا يكرر مشرف واحد (ولا من يعمل معه بلا اعتماد) السحب حتى نتيجة بعينها.
 */
beforeEach(function () {
    $this->competition = Competition::factory()->closed()->create();
    $this->manager = userWithRole(RoleName::ProgramManager);
    CompetitionSubmission::factory()->submitted()->count(4)->create(['competition_id' => $this->competition->id]);
    $this->adminA = userWithRole(RoleName::SuperAdmin);
    $this->adminB = userWithRole(RoleName::SuperAdmin);
    $this->reason = 'خطأ في قائمة المشاركات قبل السحب';
});

it('voids the first draw of a competition directly, without a second approval', function () {
    $draw = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    $void = app(VoidDraw::class)->handle($this->adminA, $draw, $this->reason);

    expect($void)->toBeInstanceOf(CompetitionDrawVoid::class)
        ->and($void->approved_by)->toBeNull()
        ->and($draw->fresh()->void)->not->toBeNull();
});

it('refuses a second void without a second super admin, and keeps the draw active', function () {
    $first = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $first, $this->reason);
    $second = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    $request = app(VoidDraw::class)->handle($this->adminB, $second, $this->reason);

    // طلب بانتظار الاعتماد، لا إلغاء
    expect($request)->toBeInstanceOf(CompetitionDrawVoidRequest::class)
        ->and($second->fresh()->void)->toBeNull()
        ->and(Activity::where('event', 'draw_void_requested')->exists())->toBeTrue();

    // والطالب لا يعتمد طلبه، ولا يكرر الطلب، ولا سحب جديد والسحب قائم
    expect(fn () => app(ApproveDrawVoid::class)->handle($this->adminB, $second))->toThrow(AuthorizationException::class)
        ->and(fn () => app(VoidDraw::class)->handle($this->adminB, $second, $this->reason))->toThrow(ValidationException::class, __('competitions.draws.void_pending'))
        ->and(fn () => app(RunDraw::class)->handle($this->manager, $this->competition, 1))->toThrow(ValidationException::class, __('competitions.draws.already_drawn'))
        ->and($second->fresh()->void)->toBeNull()
        ->and(CompetitionDrawVoid::count())->toBe(1);

    // ومن ليس مشرفاً عاماً لا يعتمد
    expect(fn () => app(ApproveDrawVoid::class)->handle($this->manager, $second))->toThrow(AuthorizationException::class);

    // واعتماد مشرف عام ثانٍ يلغيه، باسم الطالب والمعتمد
    $void = app(ApproveDrawVoid::class)->handle($this->adminA, $second);

    expect($void->voided_by)->toBe($this->adminB->id)
        ->and($void->approved_by)->toBe($this->adminA->id)
        ->and($void->reason)->toBe($this->reason)
        ->and($second->fresh()->void)->not->toBeNull()
        ->and(Activity::where('event', 'draw_void_approved')->exists())->toBeTrue()
        ->and(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $second))->toThrow(ValidationException::class);
});

it('does not let one super admin repeat the draw alone, even across several voids', function () {
    // يسحب، ويلغي، فلا يعيد السحب بنفسه
    $first = app(RunDraw::class)->handle($this->adminA, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $first, $this->reason);

    expect(fn () => app(RunDraw::class)->handle($this->adminA, $this->competition, 1))->toThrow(ValidationException::class, __('competitions.draws.voided_by_you'));

    // وبعد سحب غيره، إلغاؤه الثاني طلب لا إلغاء، فلا يُتاح سحب جديد
    $second = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $second, $this->reason);

    expect($second->fresh()->void)->toBeNull()
        ->and(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $second))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RunDraw::class)->handle($this->adminA, $this->competition, 1))->toThrow(ValidationException::class)
        ->and($this->competition->draws()->count())->toBe(2);
});

it('keeps void requests append-only in the database', function () {
    $first = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $first, $this->reason);
    $second = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $second, $this->reason);

    expect(fn () => DB::transaction(fn () => DB::table('competition_draw_void_requests')->delete()))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('competition_draw_void_requests')->update(['requested_by' => $this->adminB->id])))->toThrow(QueryException::class)
        ->and(CompetitionDrawVoidRequest::count())->toBe(1);
});

it('requests and approves a second void from the panel', function () {
    $first = app(RunDraw::class)->handle($this->manager, $this->competition, 1);
    app(VoidDraw::class)->handle($this->adminA, $first, $this->reason);
    $second = app(RunDraw::class)->handle($this->manager, $this->competition, 1);

    $this->actingAs($this->adminA);
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->callAction(TestAction::make('void')->table($second), ['reason' => $this->reason])
        ->assertNotified(__('competitions.draws.void_requested'))
        ->assertActionHidden(TestAction::make('approve_void')->table($second));

    expect($second->fresh()->void)->toBeNull();

    $this->actingAs($this->adminB);
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->callAction(TestAction::make('approve_void')->table($second))
        ->assertNotified(__('competitions.draws.voided'));

    expect($second->fresh()->void?->approved_by)->toBe($this->adminB->id);
});
