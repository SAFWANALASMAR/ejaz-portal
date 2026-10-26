<?php

use App\Actions\Assembly\RetryOnlineMeeting;
use App\Actions\Settings\TestOnlineMeetingsConnection;
use App\Actions\Settings\UpdateOnlineMeetingSettings;
use App\Contracts\OnlineMeetings;
use App\Enums\ActivityEvent;
use App\Enums\MeetingStatus;
use App\Enums\OnlineMeetingFailure;
use App\Enums\RoleName;
use App\Exceptions\OnlineMeetingFailed;
use App\Filament\Pages\OnlineMeetingsSettings;
use App\Filament\Resources\Meetings\Pages\ViewMeeting;
use App\Jobs\SyncOnlineMeeting;
use App\Models\Meeting;
use App\Models\Setting;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Support\TeamsConnection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| ربط تيمز بإعداد من اللوحة (docs/plans/teams-meetings.md): المحوّل بطلبات HTTP المحاكاة، والتعذر على
| الاجتماع مع إعادة المحاولة، وصفحة الإعداد للمشرف العام وحده، والسر لا يتسرب إلى القاعدة ولا السجل ولا
| المتصفح ولا رسائل الخطأ.
*/

beforeEach(function () {
    config(['services.online_meetings.driver' => 'graph']);
    Http::preventStrayRequests();
    $this->secret = 'Q8~teams-secret-value-0123456789';
    $this->clientId = '11111111-2222-3333-4444-555555555555';
});

/**
 * إعداد ربط تيمز محفوظ كما تحفظه اللوحة.
 *
 * @param  array<string, mixed>  $override
 */
function storeTeamsConnection(array $override = []): void
{
    Setting::put(TeamsConnection::SETTING, [
        'enabled' => true,
        'tenant_id' => 'tenant-1',
        'client_id' => '11111111-2222-3333-4444-555555555555',
        'organizer' => 'organizer@ejaz.example',
        'client_secret' => Crypt::encryptString('secret-1'),
        ...$override,
    ]);
}

function teamsToken(): array
{
    return ['login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token', 'expires_in' => 3600])];
}

function teamsTimeout(): never
{
    throw new ConnectionException('cURL error 28: Operation timed out');
}

/**
 * يجمع ما تحمله رسالة الاستثناء وسلسلته، فيُفحص أنه بلا السر ولا رد Microsoft.
 */
function failureText(Throwable $exception): string
{
    return (string) $exception;
}

it('stays off until the panel enables a complete connection, and reads it at every use', function () {
    $off = fn () => expect(fn () => app(OnlineMeetings::class))
        ->toThrow(fn (OnlineMeetingFailed $failed) => expect($failed->reason)->toBe(OnlineMeetingFailure::NotConfigured));

    $off();

    storeTeamsConnection(['enabled' => false]);
    $off();

    storeTeamsConnection(['organizer' => '']);
    $off();

    // سر لا يُفك (تغيّر مفتاح التطبيق) نقص لا خطأ
    storeTeamsConnection(['client_secret' => 'not-encrypted']);
    $off();

    storeTeamsConnection();
    expect(app(OnlineMeetings::class))->toBeInstanceOf(GraphOnlineMeetings::class);

    // التعطيل يسري على الاستعمال التالي بلا إعادة تشغيل للعمال
    storeTeamsConnection(['enabled' => false]);
    $off();
});

it('turns each Microsoft failure into a reason, without its body or the secret', function (Closure $fake, OnlineMeetingFailure $reason, ?int $status) {
    Http::fake($fake());
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', $this->secret, 'organizer@ejaz.example'));

    expect(fn () => $graph->create(Meeting::factory()->online()->create()))
        ->toThrow(function (OnlineMeetingFailed $failed) use ($reason, $status) {
            expect($failed->reason)->toBe($reason)
                ->and($failed->status)->toBe($status)
                ->and($failed->getPrevious())->toBeNull()
                ->and(failureText($failed))->not->toContain($this->secret)->not->toContain('AADSTS')->not->toContain('graph-token');
        });
})->with([
    'wrong secret' => [fn () => ['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_client', 'error_description' => 'AADSTS7000215: Invalid client secret'], 401)], OnlineMeetingFailure::InvalidCredentials, 401],
    'unknown tenant' => [fn () => ['login.microsoftonline.com/*' => Http::response(['error' => 'invalid_request', 'error_description' => 'AADSTS90002: Tenant not found'], 400)], OnlineMeetingFailure::InvalidCredentials, 400],
    'no calendar permission' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied', 'message' => 'AADSTS denied']], 403)], OnlineMeetingFailure::AccessDenied, 403],
    'unknown organizer' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidUser']], 404)], OnlineMeetingFailure::OrganizerNotFound, 404],
    'bad request' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidRequest']], 400)], OnlineMeetingFailure::InvalidRequest, 400],
    'throttled' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => Http::response([], 429)], OnlineMeetingFailure::Throttled, 429],
    'Microsoft down' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => Http::response([], 503)], OnlineMeetingFailure::ServiceError, 503],
    'login timeout' => [fn () => teamsTimeout(...), OnlineMeetingFailure::Timeout, null],
    'Graph timeout' => [fn () => [...teamsToken(), 'graph.microsoft.com/*' => teamsTimeout(...)], OnlineMeetingFailure::Timeout, null],
]);

it('keeps the token encrypted in the cache, and asks again for a changed secret', function () {
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response([], 204)]);
    $meeting = Meeting::factory()->online()->create(['online_meeting_id' => 'event-1']);

    (new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example')))->update($meeting);
    (new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example')))->update($meeting);
    (new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-2', 'organizer@ejaz.example')))->update($meeting);

    $tokens = Http::recorded(fn (Request $request) => str_contains($request->url(), '/oauth2/v2.0/token'));
    // بصمة بمفتاح التطبيق، لا بصمة السر نفسه
    $key = 'ms-graph-token:'.hash_hmac('sha256', 'tenant-1|client-1|secret-1', (string) config('app.key'));
    expect(cache()->has('ms-graph-token:'.hash('sha256', 'tenant-1|client-1|secret-1')))->toBeFalse();

    expect($tokens)->toHaveCount(2)
        ->and(cache()->get($key))->not->toContain('graph-token')
        ->and(Crypt::decryptString(cache()->get($key)))->toBe('graph-token');
});

it('keeps the secret out of dumps and of the exception trace', function () {
    $connection = new TeamsConnection('tenant-1', 'client-1', $this->secret, 'organizer@ejaz.example');

    expect(print_r($connection, true))->not->toContain($this->secret)
        ->and((new ReflectionMethod(TeamsConnection::class, '__construct'))->getParameters()[2]->getAttributes(SensitiveParameter::class))->not->toBeEmpty()
        ->and((new ReflectionMethod(UpdateOnlineMeetingSettings::class, 'handle'))->getParameters()[1]->getAttributes(SensitiveParameter::class))->not->toBeEmpty();
});

it('records a lasting failure on the meeting once, logs it, and leaves it for the admin', function () {
    storeTeamsConnection();
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403)]);
    Log::spy();
    $meeting = Meeting::factory()->online()->create();

    (new SyncOnlineMeeting($meeting->id))->handle();
    (new SyncOnlineMeeting($meeting->id))->handle();
    $meeting->refresh();

    expect($meeting->online_sync_error)->toBe(OnlineMeetingFailure::AccessDenied)
        ->and($meeting->online_sync_failed_at)->not->toBeNull()
        ->and($meeting->online_join_url)->toBeNull()
        ->and(Activity::where('event', ActivityEvent::OnlineMeetingFailed->value)->where('subject_id', $meeting->id)->count())->toBe(1)
        ->and(Activity::where('event', ActivityEvent::OnlineMeetingFailed->value)->first()->properties->all())->toBe(['reason' => 'access_denied', 'status' => 403]);
    Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context) => $context === ['meeting' => $meeting->id, 'reason' => 'access_denied', 'status' => 403])->twice();
});

it('retries a passing failure in the queue, and clears the reason once it works', function () {
    storeTeamsConnection();
    $meeting = Meeting::factory()->online()->create();
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/*' => Http::sequence()
            ->push([], 503)
            ->push(teamsEvent($meeting, ['id' => 'event-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']])),
    ]);

    expect(fn () => (new SyncOnlineMeeting($meeting->id))->handle())->toThrow(OnlineMeetingFailed::class)
        ->and($meeting->fresh()->online_sync_error)->toBe(OnlineMeetingFailure::ServiceError);

    (new SyncOnlineMeeting($meeting->id))->handle();

    expect($meeting->fresh())
        ->online_sync_error->toBeNull()
        ->online_sync_failed_at->toBeNull()
        ->online_join_url->toBe('https://teams.microsoft.com/l/meetup-join/1');
});

it('creates the Teams meeting again when its event was deleted from the calendar', function () {
    storeTeamsConnection();
    $meeting = Meeting::factory()->online()->create(['online_meeting_id' => 'gone']);
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events/gone' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events' => Http::response(teamsEvent($meeting, ['id' => 'event-2', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/2']])),
    ]);

    (new SyncOnlineMeeting($meeting->id))->handle();

    expect($meeting->fresh())
        ->online_meeting_id->toBe('event-2')
        ->online_join_url->toBe('https://teams.microsoft.com/l/meetup-join/2')
        ->online_organizer->toBe('organizer@ejaz.example')
        ->online_generation->toBe(1);
    // جيل جديد، فلا يرده منع التكرار إلى الحدث المحذوف
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/events') && $request['transactionId'] === $meeting->uuid.'-1');
});

it('updates and cancels in the calendar it was created in, even after the organizer changes', function () {
    storeTeamsConnection(['organizer' => 'new@ejaz.example']);
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response([], 204)]);
    $meeting = Meeting::factory()->online()->create(['online_meeting_id' => 'event-1', 'online_organizer' => 'old@ejaz.example']);

    (new SyncOnlineMeeting($meeting->id))->handle();
    $meeting->forceFill(['status' => MeetingStatus::Cancelled])->save();
    (new SyncOnlineMeeting($meeting->id))->handle();

    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && str_ends_with($request->url(), '/users/old%40ejaz.example/events/event-1'));
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/users/old%40ejaz.example/events/event-1'));
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), 'new%40ejaz.example'));
    expect($meeting->fresh())->online_meeting_id->toBeNull()->online_organizer->toBeNull()->online_generation->toBe(1);
});

it('cancels a deleted event as done, but keeps the link of an organizer it cannot reach for the admin', function () {
    storeTeamsConnection();
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/*' => Http::response(['error' => ['code' => 'ErrorItemNotFound']], 404),
        'graph.microsoft.com/v1.0/users/gone%40ejaz.example/*' => Http::response(['error' => ['code' => 'ErrorInvalidUser']], 404),
    ]);
    $cancelled = Meeting::factory()->online()->create(['online_meeting_id' => 'gone', 'status' => MeetingStatus::Cancelled]);
    $unreachable = Meeting::factory()->online()->create(['online_meeting_id' => 'event-1', 'online_organizer' => 'gone@ejaz.example', 'status' => MeetingStatus::Cancelled]);

    (new SyncOnlineMeeting($cancelled->id))->handle();
    (new SyncOnlineMeeting($unreachable->id))->handle();

    expect($cancelled->fresh())->online_meeting_id->toBeNull()->online_sync_error->toBeNull()
        ->and($unreachable->fresh())->online_meeting_id->toBe('event-1')->online_sync_error->toBe(OnlineMeetingFailure::OrganizerNotFound);
});

it('shows an unexpected error after the last attempt', function () {

    $meeting = Meeting::factory()->online()->create();
    (new SyncOnlineMeeting($meeting->id))->failed(new RuntimeException('boom'));
    expect($meeting->fresh()->online_sync_error)->toBe(OnlineMeetingFailure::Unexpected);
});

it('touches no Teams settings for a meeting in a hall', function () {
    $meeting = Meeting::factory()->create();

    (new SyncOnlineMeeting($meeting->id))->handle();

    expect($meeting->fresh()->online_sync_error)->toBeNull();
    Http::assertNothingSent();
});

it('lets the secretary retry a failed meeting, logged, and refuses the board and others', function () {
    Bus::fake();
    $meeting = Meeting::factory()->online()->create();
    $meeting->forceFill(['online_sync_error' => OnlineMeetingFailure::NotConfigured, 'online_sync_failed_at' => now()])->save();

    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::AssemblyMember] as $role) {
        expect(fn () => app(RetryOnlineMeeting::class)->handle(userWithRole($role), $meeting))->toThrow(AuthorizationException::class);
    }

    $secretary = userWithRole(RoleName::Secretary);
    app(RetryOnlineMeeting::class)->handle($secretary, $meeting);

    Bus::assertDispatched(SyncOnlineMeeting::class, fn (SyncOnlineMeeting $job) => $job->meetingId === $meeting->id);
    expect($meeting->fresh()->online_sync_error)->toBeNull()
        ->and(Activity::where('event', ActivityEvent::OnlineMeetingRetried->value)->where('causer_id', $secretary->id)->exists())->toBeTrue();

    // المطابق لتيمز لا يحتاج إعادة
    $linked = Meeting::factory()->online()->create(['online_meeting_id' => 'event-1']);
    expect(fn () => app(RetryOnlineMeeting::class)->handle($secretary, $linked))->toThrow(ValidationException::class);
});

it('shows the failure and the retry button in the panel to the secretary only', function () {
    Bus::fake();
    $meeting = Meeting::factory()->online()->create();
    $meeting->forceFill(['online_sync_error' => OnlineMeetingFailure::AccessDenied, 'online_sync_failed_at' => now()])->save();

    $this->actingAs(userWithRole(RoleName::BoardMember));
    Livewire::test(ViewMeeting::class, ['record' => $meeting->uuid])
        ->assertSee(OnlineMeetingFailure::AccessDenied->getLabel())
        ->assertActionHidden('retryOnline');

    $this->actingAs(userWithRole(RoleName::Secretary));
    Livewire::test(ViewMeeting::class, ['record' => $meeting->uuid])
        ->assertSee(__('assembly.online_failure_hints.access_denied'))
        ->callAction('retryOnline')
        ->assertNotified(__('assembly.notices.online_retry'));

    Bus::assertDispatched(SyncOnlineMeeting::class);
});

it('opens the Teams settings to the administrators alone', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get(OnlineMeetingsSettings::getUrl())->assertOk();

    foreach ([RoleName::Secretary, RoleName::ContentEditor, RoleName::BoardMember] as $role) {
        $this->actingAs(userWithRole($role))->get(OnlineMeetingsSettings::getUrl())->assertForbidden();
    }

    expect(fn () => app(UpdateOnlineMeetingSettings::class)->handle(userWithRole(RoleName::Secretary), ['enabled' => false]))->toThrow(AuthorizationException::class)
        ->and(fn () => app(TestOnlineMeetingsConnection::class)->handle(userWithRole(RoleName::ContentEditor)))->toThrow(AuthorizationException::class);
});

it('saves the secret encrypted, never logs it, and never sends it back to the browser', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $this->actingAs($admin);

    $page = Livewire::test(OnlineMeetingsSettings::class)
        ->fillForm(['enabled' => true, 'tenant_id' => 'ejaz.onmicrosoft.com', 'client_id' => $this->clientId, 'client_secret' => $this->secret, 'organizer' => 'organizer@ejaz.example'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertSet('data.client_secret', null);

    $snapshot = json_encode((fn () => $this->lastState->getSnapshot())->call($page));

    expect(TeamsConnection::current()?->secret())->toBe($this->secret)
        ->and(DB::table('settings')->where('key', TeamsConnection::SETTING)->value('value'))->not->toContain($this->secret)
        ->and($snapshot)->not->toContain($this->secret)
        ->and($page->html())->not->toContain($this->secret)
        ->and(Activity::where('subject_type', Setting::class)->exists())->toBeFalse()
        ->and(Activity::all()->toJson())->not->toContain($this->secret);

    $logged = Activity::where('event', ActivityEvent::OnlineMeetingsSettingsUpdated->value)->firstOrFail();
    expect($logged->causer_id)->toBe($admin->id)
        ->and($logged->properties->all())->toBe(['enabled' => true, 'tenant_id' => 'ejaz.onmicrosoft.com', 'client_id' => $this->clientId, 'organizer' => 'organizer@ejaz.example', 'secret_changed' => true]);

    // الفارغ يُبقي المحفوظ، والصفحة لا تعرضه
    Livewire::test(OnlineMeetingsSettings::class)
        ->assertSet('data.client_secret', null)
        ->assertSee(__('settings.online_meetings.secret_saved'))
        ->fillForm(['organizer' => 'meetings@ejaz.example'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(TeamsConnection::current())->secret()->toBe($this->secret)->organizer->toBe('meetings@ejaz.example')
        ->and(Activity::where('event', ActivityEvent::OnlineMeetingsSettingsUpdated->value)->latest('id')->first()->properties['secret_changed'])->toBeFalse();
});

it('refuses an incomplete or malformed connection, and drops the typed secret anyway', function (array $data, string $field) {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    Livewire::test(OnlineMeetingsSettings::class)
        ->fillForm(['enabled' => true, 'tenant_id' => 'tenant-1.example', 'client_id' => $this->clientId, 'organizer' => 'organizer@ejaz.example', 'client_secret' => $this->secret, ...$data])
        ->call('save')
        ->assertHasErrors(['data.'.$field])
        ->assertSet('data.client_secret', null);

    expect(Setting::group(TeamsConnection::SETTING))->toBe([]);
})->with([
    'enabled without a secret' => [['client_secret' => null], 'client_secret'],
    'client id not a GUID' => [['client_id' => 'my-app'], 'client_id'],
    'tenant with a path' => [['tenant_id' => 'tenant/../x'], 'tenant_id'],
    'organizer not an email' => [['organizer' => 'organizer'], 'organizer'],
    'enabled without an organizer' => [['organizer' => null], 'organizer'],
]);

it('tests the saved connection with a fresh token and reports the reason', function () {
    $admin = userWithRole(RoleName::TechnicalAdmin);
    $this->actingAs($admin);

    Livewire::test(OnlineMeetingsSettings::class)->call('testConnection')->assertNotified(OnlineMeetingFailure::NotConfigured->getLabel());

    // مطفأ ويُختبر قبل التفعيل
    storeTeamsConnection(['enabled' => false]);
    cache()->put('ms-graph-token:'.hash_hmac('sha256', 'tenant-1|'.$this->clientId.'|secret-1', (string) config('app.key')), Crypt::encryptString('stale-token'));
    Http::fake([...teamsToken(), 'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/calendar*' => Http::sequence()->push(['id' => 'calendar-1'])->push(['error' => ['code' => 'ErrorAccessDenied']], 403)]);

    Livewire::test(OnlineMeetingsSettings::class)->call('testConnection')->assertNotified(__('settings.online_meetings.test_ok'));
    Livewire::test(OnlineMeetingsSettings::class)->call('testConnection')->assertNotified(OnlineMeetingFailure::AccessDenied->getLabel());

    Http::assertSent(fn (Request $request) => str_contains($request->url(), '/oauth2/v2.0/token'));
    Http::assertNotSent(fn (Request $request) => $request->hasHeader('Authorization', 'Bearer stale-token'));
    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['POST', 'PATCH', 'DELETE'], true) && str_contains($request->url(), 'graph.microsoft.com'));

    // بترتيب الإدراج صراحة، فترتيب PostgreSQL بلا ORDER BY غير مضمون (فشل متقطع في الفحص الآلي)
    expect(Activity::where('event', ActivityEvent::OnlineMeetingsConnectionTested->value)->orderBy('id')->pluck('properties')->map->get('result')->all())
        ->toBe(['not_configured', 'ok', 'access_denied']);
});

it('drops a secret typed before testing, and limits tests to five a minute', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    $page = Livewire::test(OnlineMeetingsSettings::class)
        ->set('data.client_secret', $this->secret)
        ->call('testConnection')
        ->assertSet('data.client_secret', null);

    expect(json_encode((fn () => $this->lastState->getSnapshot())->call($page)))->not->toContain($this->secret);

    foreach (range(1, 4) as $attempt) {
        $page->call('testConnection');
    }

    $page->call('testConnection')->assertNotified(__('settings.online_meetings.test_throttled'));
    expect(Activity::where('event', ActivityEvent::OnlineMeetingsConnectionTested->value)->count())->toBe(5);
});

it('retries every failed meeting once Teams is enabled, and warns when switched off with live links', function () {
    Bus::fake();
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));
    $failed = Meeting::factory()->online()->create();
    $failed->forceFill(['online_sync_error' => OnlineMeetingFailure::NotConfigured])->save();
    Meeting::factory()->online()->create(['online_meeting_id' => 'event-1']);

    Livewire::test(OnlineMeetingsSettings::class)
        ->fillForm(['enabled' => true, 'tenant_id' => 'tenant-1.example', 'client_id' => $this->clientId, 'client_secret' => $this->secret, 'organizer' => 'organizer@ejaz.example'])
        ->call('save')
        ->assertHasNoFormErrors();

    Bus::assertDispatchedTimes(SyncOnlineMeeting::class, 1);
    Bus::assertDispatched(SyncOnlineMeeting::class, fn (SyncOnlineMeeting $job) => $job->meetingId === $failed->id);

    Livewire::test(OnlineMeetingsSettings::class)
        ->fillForm(['enabled' => false])
        ->call('save')
        ->assertNotified(__('settings.online_meetings.disabled_with_links', ['count' => '1']));

    Bus::assertDispatchedTimes(SyncOnlineMeeting::class, 1);
});

it('brings an event returned by a retried create up to date, and leaves a current one alone', function () {
    $meeting = Meeting::factory()->online()->create(['title' => 'العنوان الحالي']);
    $stale = teamsEvent($meeting, ['id' => 'event-1', 'subject' => 'عنوان قديم', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']]);
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events' => Http::sequence()
            ->push($stale)
            ->push(teamsEvent($meeting, ['id' => 'event-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']])),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events/event-1' => Http::response([], 200),
    ]);
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example'));

    $graph->create($meeting);
    expect(Http::recorded(fn (Request $request) => $request->method() === 'PATCH'))->toHaveCount(1);
    Http::assertSent(fn (Request $request) => $request->method() === 'PATCH' && $request['subject'] === 'العنوان الحالي');

    $graph->create($meeting);
    expect(Http::recorded(fn (Request $request) => $request->method() === 'PATCH'))->toHaveCount(1);
});

it('recovers and deletes an event whose create timed out once the meeting is cancelled', function () {
    storeTeamsConnection();
    $meeting = Meeting::factory()->online()->create();
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events' => Http::sequence()
            ->pushFailedConnection()
            ->push(teamsEvent($meeting, ['id' => 'event-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']])),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events/event-1' => Http::response([], 204),
    ]);

    expect(fn () => (new SyncOnlineMeeting($meeting->id))->handle())->toThrow(OnlineMeetingFailed::class);
    expect($meeting->fresh())->online_create_pending->toBeTrue()->online_meeting_id->toBeNull()->needsOnlineSync()->toBeTrue();

    $meeting->forceFill(['status' => MeetingStatus::Cancelled])->save();
    (new SyncOnlineMeeting($meeting->id))->handle();

    $posts = Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/events'));
    expect(collect($posts)->map(fn ($pair) => $pair[0]['transactionId'])->unique()->values()->all())->toBe([$meeting->uuid.'-0']);
    Http::assertSent(fn (Request $request) => $request->method() === 'DELETE' && str_ends_with($request->url(), '/events/event-1'));
    expect($meeting->fresh())
        ->online_meeting_id->toBeNull()
        ->online_create_pending->toBeFalse()
        ->online_generation->toBe(1)
        ->needsOnlineSync()->toBeFalse();
});

it('drops a cached token that Graph refuses, so the next try asks for a new one', function (int $status) {
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response([], $status)]);
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example'));
    $meeting = Meeting::factory()->online()->create(['online_meeting_id' => 'event-1']);

    expect(fn () => $graph->update($meeting))->toThrow(OnlineMeetingFailed::class)
        ->and(fn () => $graph->update($meeting))->toThrow(OnlineMeetingFailed::class)
        ->and(cache()->has('ms-graph-token:'.hash_hmac('sha256', 'tenant-1|client-1|secret-1', (string) config('app.key'))))->toBeFalse()
        ->and(Http::recorded(fn (Request $request) => str_contains($request->url(), '/oauth2/v2.0/token')))->toHaveCount(2);
})->with([401, 403]);

it('does not retry a request Graph rejects as invalid', function () {
    storeTeamsConnection();
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidRequest']], 400)]);
    $meeting = Meeting::factory()->online()->create();

    (new SyncOnlineMeeting($meeting->id))->handle();

    expect($meeting->fresh()->online_sync_error)->toBe(OnlineMeetingFailure::InvalidRequest)
        ->and(OnlineMeetingFailure::InvalidRequest->isTransient())->toBeFalse();
});

it('warns on the settings page when the server does not use the graph driver', function () {
    $this->actingAs(userWithRole(RoleName::TechnicalAdmin));

    config(['services.online_meetings.driver' => 'fake']);
    Livewire::test(OnlineMeetingsSettings::class)->assertSee(__('settings.online_meetings.driver_warning'));

    config(['services.online_meetings.driver' => 'graph']);
    Livewire::test(OnlineMeetingsSettings::class)->assertDontSee(__('settings.online_meetings.driver_warning'));
});

it('clears the pending create after a final refusal of the create itself, so a later cancel has nothing to recover', function () {
    storeTeamsConnection();
    $meeting = Meeting::factory()->online()->create();
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidRequest']], 400)]);

    (new SyncOnlineMeeting($meeting->id))->handle();
    expect($meeting->fresh())->online_create_pending->toBeFalse()->online_sync_error->toBe(OnlineMeetingFailure::InvalidRequest);

    $meeting->forceFill(['status' => MeetingStatus::Cancelled])->save();
    $sent = count(Http::recorded());
    (new SyncOnlineMeeting($meeting->id))->handle();

    expect(Http::recorded())->toHaveCount($sent)
        ->and($meeting->fresh())->online_sync_error->toBeNull()->needsOnlineSync()->toBeFalse();
});

it('ends a recovery the create refuses for good, and keeps the mark when the event may exist', function () {
    storeTeamsConnection();
    // انتهت مهلة الإنشاء ثم أُلغي الاجتماع، فرُفض طلب الاسترداد نهائياً: لا حدث ولا حذف
    $refused = Meeting::factory()->online()->create(['status' => MeetingStatus::Cancelled, 'online_create_pending' => true]);
    Http::fake([...teamsToken(), 'graph.microsoft.com/*' => Http::response(['error' => ['code' => 'ErrorInvalidRequest']], 400)]);

    (new SyncOnlineMeeting($refused->id))->handle();

    expect($refused->fresh())->online_create_pending->toBeFalse()->online_sync_error->toBeNull()->needsOnlineSync()->toBeFalse();
    Http::assertNotSent(fn (Request $request) => $request->method() === 'DELETE');
});

it('keeps the pending create when the event was made but its link could not be read', function () {
    storeTeamsConnection();
    $meeting = Meeting::factory()->online()->create();
    Http::fake([
        ...teamsToken(),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events' => Http::response(teamsEvent($meeting, ['id' => 'event-1'])),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events/event-1' => Http::response(['error' => ['code' => 'ErrorAccessDenied']], 403),
    ]);

    (new SyncOnlineMeeting($meeting->id))->handle();

    expect($meeting->fresh())->online_create_pending->toBeTrue()->online_sync_error->toBe(OnlineMeetingFailure::AccessDenied);
});
