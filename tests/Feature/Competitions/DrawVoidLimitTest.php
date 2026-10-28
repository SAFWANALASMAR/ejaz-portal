<?php

use App\Actions\Competitions\ApproveDrawVoid;
use App\Actions\Competitions\RejectDrawVoid;
use App\Actions\Competitions\RunDraw;
use App\Actions\Competitions\VoidDraw;
use App\Enums\DrawVoidRequestStatus;
use App\Enums\RoleName;
use App\Filament\Resources\Competitions\Pages\ViewCompetition;
use App\Filament\Resources\Competitions\RelationManagers\DrawsRelationManager;
use App\Models\Competition;
use App\Models\CompetitionDraw;
use App\Models\CompetitionDrawVoid;
use App\Models\CompetitionDrawVoidRequest;
use App\Models\CompetitionSubmission;
use App\Notifications\DrawVoidRequestClosed;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
    $this->adminA = userWithRole(RoleName::TechnicalAdmin);
    $this->adminB = userWithRole(RoleName::TechnicalAdmin);
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
        ->and($request->fresh()->status)->toBe(DrawVoidRequestStatus::Approved)
        ->and($request->fresh()->decided_by)->toBe($this->adminA->id)
        ->and(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $second))->toThrow(ValidationException::class)
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $second->fresh(), $this->reason))->toThrow(AuthorizationException::class)
        // وإن مرّ فحص الصلاحية بنسخة قديمة، فالإجراء يعيد قراءة الطلب تحت القفل
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $second, $this->reason))->toThrow(ValidationException::class, __('competitions.draws.void_request_closed'));
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

/*
 * قرار صفوان (14 أكتوبر 2026): يرفض الطلبَ مشرفٌ عام غير طالبه بسبب إلزامي، ويسقط بعد سبعة أيام بلا قرار.
 * وفي الحالتين يبقى السحب معتمداً، ويُسجَّل القرار، ويُبلَّغ الطالب.
 */
function drawWithPendingVoidRequest(object $test): CompetitionDraw
{
    $first = app(RunDraw::class)->handle($test->manager, $test->competition, 1);
    app(VoidDraw::class)->handle($test->adminA, $first, $test->reason);
    $second = app(RunDraw::class)->handle($test->manager, $test->competition, 1);
    app(VoidDraw::class)->handle($test->adminB, $second, $test->reason);

    return $second;
}

it('lets another super admin reject a void request with a reason, and keeps the draw active', function () {
    Notification::fake();
    $draw = drawWithPendingVoidRequest($this);
    $request = $draw->voidRequest()->firstOrFail();

    app(RejectDrawVoid::class)->handle($this->adminA, $draw, 'السحب سليم وقائمة المشاركات صحيحة');

    $request->refresh();
    expect($request->status)->toBe(DrawVoidRequestStatus::Rejected)
        ->and($request->decided_by)->toBe($this->adminA->id)
        ->and($request->decision_reason)->toBe('السحب سليم وقائمة المشاركات صحيحة')
        ->and($draw->fresh()->void)->toBeNull()
        ->and(Activity::where('event', 'draw_void_rejected')->where('causer_id', $this->adminA->id)->exists())->toBeTrue()
        ->and(fn () => app(RunDraw::class)->handle($this->manager, $this->competition, 1))->toThrow(ValidationException::class, __('competitions.draws.already_drawn'));

    Notification::assertSentTo($this->adminB, DrawVoidRequestClosed::class, fn (DrawVoidRequestClosed $notification): bool => $notification->status === DrawVoidRequestStatus::Rejected);

    // والمرفوض لا يُعتمد ولا يُرفض ثانية، ويُتاح بعده طلب جديد يحتاج قراراً جديداً
    expect(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $draw->fresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $draw->fresh(), $this->reason))->toThrow(AuthorizationException::class)
        ->and(app(VoidDraw::class)->handle($this->adminB, $draw->fresh(), $this->reason))->toBeInstanceOf(CompetitionDrawVoidRequest::class)
        ->and($draw->fresh()->void)->toBeNull();
});

it('refuses a rejection without a reason, by the requester, or by a non super admin', function () {
    $draw = drawWithPendingVoidRequest($this);

    expect(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $draw, '   '))->toThrow(ValidationException::class, __('competitions.draws.reject_reason_rule'))
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $draw, 'قصير'))->toThrow(ValidationException::class, __('competitions.draws.reject_reason_rule'))
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminB, $draw, $this->reason))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->manager, $draw, $this->reason))->toThrow(AuthorizationException::class)
        ->and($draw->voidRequest()->firstOrFail()->status)->toBe(DrawVoidRequestStatus::Pending)
        ->and(Activity::where('event', 'draw_void_rejected')->exists())->toBeFalse();
});

it('lapses a void request nobody decided within seven days, keeps the draw, logs it and notifies the requester', function () {
    Notification::fake();
    $draw = drawWithPendingVoidRequest($this);
    $request = $draw->voidRequest()->firstOrFail();

    // قبل مضي المدة لا يسقط
    $this->travel(CompetitionDrawVoidRequest::LAPSE_DAYS - 1)->days();
    $this->artisan('ejaz:lapse-draw-void-requests')->assertSuccessful();
    expect($request->fresh()->status)->toBe(DrawVoidRequestStatus::Pending);

    $this->travel(1)->days();
    $this->travel(1)->hours();
    $this->artisan('ejaz:lapse-draw-void-requests')->assertSuccessful();
    $this->artisan('ejaz:lapse-draw-void-requests')->assertSuccessful();

    $request->refresh();
    expect($request->status)->toBe(DrawVoidRequestStatus::Lapsed)
        ->and($request->decided_by)->toBeNull()
        ->and($request->decided_at)->not->toBeNull()
        ->and($draw->fresh()->void)->toBeNull()
        ->and(Activity::where('event', 'draw_void_lapsed')->whereNull('causer_id')->count())->toBe(1);

    Notification::assertSentToTimes($this->adminB, DrawVoidRequestClosed::class, 1);
    Notification::assertSentTo($this->adminB, DrawVoidRequestClosed::class, fn (DrawVoidRequestClosed $notification): bool => $notification->status === DrawVoidRequestStatus::Lapsed);

    // والساقط لا يُعتمد ولا يُرفض
    expect(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $draw->fresh()))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $draw->fresh(), $this->reason))->toThrow(AuthorizationException::class)
        ->and($draw->fresh()->void)->toBeNull();
});

it('lapses a request past its time on the first decision attempt, before the daily command runs', function () {
    Notification::fake();
    $draw = drawWithPendingVoidRequest($this);
    $request = $draw->voidRequest()->firstOrFail();
    $this->travel(CompetitionDrawVoidRequest::LAPSE_DAYS)->days();
    $this->travel(1)->hours();

    expect(fn () => app(ApproveDrawVoid::class)->handle($this->adminA, $draw))->toThrow(ValidationException::class, __('competitions.draws.void_request_lapsed'));

    // سقط الآن وحُفظ سقوطه، لا عند الأمر اليومي
    expect($request->fresh()->status)->toBe(DrawVoidRequestStatus::Lapsed)
        ->and(Activity::where('event', 'draw_void_lapsed')->count())->toBe(1)
        ->and($draw->fresh()->void)->toBeNull();
    Notification::assertSentToTimes($this->adminB, DrawVoidRequestClosed::class, 1);

    // ولا طلب معلّقاً بعده يُرفض
    expect(fn () => app(RejectDrawVoid::class)->handle($this->adminA, $draw->fresh(), $this->reason))->toThrow(AuthorizationException::class);

    $this->artisan('ejaz:lapse-draw-void-requests')->assertSuccessful();
    expect(Activity::where('event', 'draw_void_lapsed')->count())->toBe(1);
});

it('refuses a non super admin before lapsing anything', function () {
    $draw = drawWithPendingVoidRequest($this);
    $request = $draw->voidRequest()->firstOrFail();
    $this->travel(CompetitionDrawVoidRequest::LAPSE_DAYS)->days();
    $this->travel(1)->hours();

    expect(fn () => app(ApproveDrawVoid::class)->handle($this->manager, $draw))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->manager, $draw, $this->reason))->toThrow(AuthorizationException::class)
        ->and($request->fresh()->status)->toBe(DrawVoidRequestStatus::Pending)
        ->and(Activity::where('event', 'draw_void_lapsed')->exists())->toBeFalse();
});

it('lapses a request past its time when a new void is asked for, instead of refusing it as pending', function () {
    Notification::fake();
    $draw = drawWithPendingVoidRequest($this);
    $old = $draw->voidRequest()->firstOrFail();
    $this->travel(CompetitionDrawVoidRequest::LAPSE_DAYS)->days();
    $this->travel(1)->hours();

    $new = app(VoidDraw::class)->handle($this->adminA, $draw, $this->reason);

    expect($old->fresh()->status)->toBe(DrawVoidRequestStatus::Lapsed)
        ->and($new)->toBeInstanceOf(CompetitionDrawVoidRequest::class)
        ->and($new->requested_by)->toBe($this->adminA->id)
        ->and($draw->fresh()->voidRequest->id)->toBe($new->id);
});

it('hides the decision buttons of a request past its time, and offers the void button again', function () {
    $draw = drawWithPendingVoidRequest($this);
    $this->travel(CompetitionDrawVoidRequest::LAPSE_DAYS)->days();
    $this->travel(1)->hours();

    $this->actingAs($this->adminA);
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('approve_void')->table($draw))
        ->assertActionHidden(TestAction::make('reject_void')->table($draw))
        ->assertActionVisible(TestAction::make('void')->table($draw));
});

it('refuses a decision by the requester found under the lock with a message, not a database error', function () {
    $draw = drawWithPendingVoidRequest($this);
    // الصلاحية فُحصت على طلب غير الذي وُجد تحت القفل (سباق): يُحاكى بتجاوز الصلاحية لطالب الطلب نفسه
    Gate::before(fn ($user, string $ability) => in_array($ability, ['approveVoid', 'rejectVoid'], true) ? true : null);

    expect(fn () => app(ApproveDrawVoid::class)->handle($this->adminB, $draw))->toThrow(ValidationException::class, __('competitions.draws.void_own_request'))
        ->and(fn () => app(RejectDrawVoid::class)->handle($this->adminB, $draw, $this->reason))->toThrow(ValidationException::class, __('competitions.draws.void_own_request'))
        ->and($draw->voidRequest()->firstOrFail()->status)->toBe(DrawVoidRequestStatus::Pending);
});

it('records a decision once, and the database refuses to change it or let the requester decide', function () {
    $draw = drawWithPendingVoidRequest($this);
    $id = $draw->voidRequest()->firstOrFail()->id;

    // القرار من الطالب نفسه ترفضه قاعدة البيانات أيضاً
    expect(fn () => DB::transaction(fn () => DB::table('competition_draw_void_requests')->where('id', $id)
        ->update(['status' => 'rejected', 'decided_by' => $this->adminB->id, 'decision_reason' => $this->reason, 'decided_at' => now()])))->toThrow(QueryException::class);

    app(RejectDrawVoid::class)->handle($this->adminA, $draw, $this->reason);

    expect(fn () => DB::transaction(fn () => DB::table('competition_draw_void_requests')->where('id', $id)
        ->update(['status' => 'pending', 'decided_by' => null, 'decision_reason' => null, 'decided_at' => null])))->toThrow(QueryException::class)
        ->and(fn () => DB::transaction(fn () => DB::table('competition_draw_void_requests')->where('id', $id)->update(['decision_reason' => 'سبب آخر بعد القرار'])))->toThrow(QueryException::class)
        ->and(CompetitionDrawVoidRequest::findOrFail($id)->status)->toBe(DrawVoidRequestStatus::Rejected);
});

it('rejects a void request from the panel, with the reason required, and hides it from the requester', function () {
    $draw = drawWithPendingVoidRequest($this);

    $this->actingAs($this->adminB);
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->assertActionHidden(TestAction::make('reject_void')->table($draw));

    $this->actingAs($this->adminA);
    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->callAction(TestAction::make('reject_void')->table($draw), ['reason' => ''])
        ->assertHasActionErrors(['reason']);

    expect($draw->voidRequest()->firstOrFail()->status)->toBe(DrawVoidRequestStatus::Pending);

    Livewire::test(DrawsRelationManager::class, ['ownerRecord' => $this->competition, 'pageClass' => ViewCompetition::class])
        ->callAction(TestAction::make('reject_void')->table($draw), ['reason' => 'السحب سليم وقائمة المشاركات صحيحة'])
        ->assertNotified(__('competitions.draws.void_rejected'));

    expect(CompetitionDrawVoidRequest::where('draw_id', $draw->id)->firstOrFail()->status)->toBe(DrawVoidRequestStatus::Rejected);
});

it('schedules the lapse command daily', function () {
    $this->artisan('schedule:list')->assertSuccessful();

    $event = collect(app(Schedule::class)->events())->first(fn (Event $event): bool => str_contains((string) $event->command, 'ejaz:lapse-draw-void-requests'));

    expect($event?->expression)->toBe('0 0 * * *');
});
