<?php

use App\Actions\Assembly\CancelMeeting;
use App\Actions\Assembly\MarkMeetingHeld;
use App\Actions\Assembly\RecordMeetingAttendance;
use App\Actions\Assembly\SaveMeeting;
use App\Contracts\OnlineMeetings;
use App\Enums\MeetingStatus;
use App\Enums\MembershipStatus;
use App\Enums\OnlineMeetingFailure;
use App\Enums\RoleName;
use App\Jobs\SyncOnlineMeeting;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Notifications\MeetingNotice;
use App\Services\OnlineMeetings\FakeOnlineMeetings;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Support\TeamsConnection;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| اجتماعات الجمعية العمومية (requirements/04): يديرها السكرتير، وتُدعى إليها الأعضاء الفعّالون، وتُربط
| بتيمز خلف واجهة بتطبيق وهمي، ويُسجَّل حضورها ويُحسب نصابها.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->data = [
        'title' => 'الاجتماع العادي التجريبي',
        'type' => 'ordinary',
        'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeeks(2)->addHours(2)->format('Y-m-d H:i:s'),
        'location' => 'قاعة تجريبية',
        'is_online' => false,
        'agenda' => 'بند تجريبي',
        'quorum_percent' => 50,
        'attachments' => [],
    ];
});

/**
 * مرفق PDF حقيقي في مجلد المرفقات، كما يرفعه نموذج اللوحة.
 *
 * @return array{path: string, name: string}
 */
function meetingAttachment(string $contents = ''): array
{
    $path = storedPrivateFile(Meeting::ATTACHMENTS_DIRECTORY, $contents === '' ? null : $contents);

    return ['path' => $path, 'name' => 'جدول الأعمال.pdf'];
}

it('lets the secretary schedule a meeting, and refuses board members and other accounts', function () {
    foreach ([RoleName::BoardMember, RoleName::ContentEditor, RoleName::AssemblyMember] as $role) {
        expect(fn () => app(SaveMeeting::class)->handle(userWithRole($role), new Meeting, $this->data))->toThrow(AuthorizationException::class);
    }

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'attachments' => [meetingAttachment()]]);

    expect($meeting->status)->toBe(MeetingStatus::Scheduled)
        ->and($meeting->created_by)->toBe($this->secretary->id)
        ->and($meeting->attachments)->toHaveCount(1)
        ->and($meeting->attachments[0]['size'])->toBeGreaterThan(0)
        ->and(Activity::where('subject_type', Meeting::class)->where('event', 'created')->exists())->toBeTrue();
});

it('requires a place or an online meeting, an end time for an online one, and real PDF attachments', function (array $override, string $field) {
    expect(fn () => app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, ...$override]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));

    expect(Meeting::count())->toBe(0);
})->with([
    'no place' => [['location' => null, 'is_online' => false], 'location'],
    'online without an end' => [['is_online' => true, 'ends_at' => null], 'ends_at'],
    'end before start' => [['ends_at' => now()->addWeek()->format('Y-m-d H:i:s')], 'ends_at'],
    'quorum above 100' => [['quorum_percent' => 101], 'quorum_percent'],
    'fake PDF' => [fn () => ['attachments' => [meetingAttachment('not a pdf')]], 'attachments'],
    'file outside the folder' => [['attachments' => [['path' => 'service-requests/x.pdf', 'name' => 'x.pdf']]], 'attachments'],
]);

it('invites active members only, by email with the portal page and never the Teams link', function () {
    Notification::fake();
    $active = AssemblyMembership::factory()->create()->user;
    $suspended = AssemblyMembership::factory()->status(MembershipStatus::Suspended)->create()->user;
    $board = userWithRole(RoleName::BoardMember);

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => true, 'location' => null]);

    Notification::assertSentTo($active, MeetingNotice::class, fn (MeetingNotice $notice) => $notice->kind === 'scheduled' && $notice->meeting->is($meeting));
    Notification::assertNotSentTo([$suspended, $board, $this->secretary], MeetingNotice::class);

    $meeting->forceFill(['online_join_url' => 'https://teams.example.test/secret'])->save();
    $mail = (new MeetingNotice($meeting->fresh(), 'scheduled'))->toMail($active)->render();

    expect((string) $mail)->toContain(route('assembly.meetings.show', $meeting))
        ->not->toContain('teams.example.test/secret');
});

it('tells members when the time or place changes, not for an agenda typo, and when the meeting is cancelled', function () {
    $member = AssemblyMembership::factory()->create()->user;
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data);
    Notification::fake();

    app(SaveMeeting::class)->handle($this->secretary, $meeting, [...$this->data, 'agenda' => 'بند مصحح']);
    Notification::assertNothingSent();

    app(SaveMeeting::class)->handle($this->secretary, $meeting->fresh(), [...$this->data, 'location' => 'قاعة أخرى']);
    Notification::assertSentTo($member, MeetingNotice::class, fn (MeetingNotice $notice) => $notice->kind === 'updated');

    app(CancelMeeting::class)->handle($this->secretary, $meeting->fresh());
    Notification::assertSentTo($member, MeetingNotice::class, fn (MeetingNotice $notice) => $notice->kind === 'cancelled');

    expect($meeting->fresh()->status)->toBe(MeetingStatus::Cancelled)
        ->and(fn () => app(SaveMeeting::class)->handle($this->secretary, $meeting->fresh(), $this->data))->toThrow(AuthorizationException::class)
        ->and(fn () => app(CancelMeeting::class)->handle($this->secretary, $meeting->fresh()))->toThrow(AuthorizationException::class);
});

it('creates, updates and cancels the Teams meeting in the background, one sync at a time', function () {
    app()->instance(OnlineMeetings::class, $teams = new FakeOnlineMeetings);

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => true]);
    $meeting->refresh();

    expect($meeting->online_meeting_id)->toBe('fake-'.$meeting->uuid)
        ->and($meeting->online_join_url)->toBe('https://teams.example.test/meet/'.$meeting->uuid)
        // مشفّر في القاعدة
        ->and(DB::table('meetings')->where('id', $meeting->id)->value('online_join_url'))->not->toContain('teams.example.test');

    app(SaveMeeting::class)->handle($this->secretary, $meeting, [...$this->data, 'is_online' => true, 'starts_at' => now()->addWeeks(3)->format('Y-m-d H:i:s'), 'ends_at' => now()->addWeeks(3)->addHour()->format('Y-m-d H:i:s')]);
    app(CancelMeeting::class)->handle($this->secretary, $meeting->fresh());

    expect($teams->calls)->toBe(['create:'.$meeting->uuid, 'update:'.$meeting->uuid, 'cancel:'.$meeting->uuid])
        ->and($meeting->fresh()->online_join_url)->toBeNull()
        ->and((new SyncOnlineMeeting($meeting->id))->middleware()[0]->key)->toBe((string) $meeting->id);
});

it('dispatches no Teams sync for a meeting in a hall', function () {
    Bus::fake();

    app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data);

    Bus::assertNotDispatched(SyncOnlineMeeting::class);
});

it('saves an online meeting while Teams is switched off, and shows why it has no link', function () {
    Notification::fake();
    $member = AssemblyMembership::factory()->create()->user;
    config(['services.online_meetings.driver' => 'graph']);

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => true])->fresh();

    expect($meeting->exists)->toBeTrue()
        ->and($meeting->online_join_url)->toBeNull()
        ->and($meeting->online_sync_error)->toBe(OnlineMeetingFailure::NotConfigured)
        ->and($meeting->needsOnlineSync())->toBeTrue();
    Notification::assertSentTo($member, MeetingNotice::class);

    // والاجتماع في القاعة لا يمس الربط
    expect(app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data)->fresh()->online_sync_error)->toBeNull();
});

it('refuses the fake Teams driver in production', function () {
    app()->detectEnvironment(fn () => 'production');
    app()->forgetInstance(OnlineMeetings::class);
    config(['services.online_meetings.driver' => 'fake']);

    expect(fn () => app(OnlineMeetings::class))->toThrow(RuntimeException::class)
        ->and(fn () => (new FakeOnlineMeetings)->create(Meeting::factory()->make()))->toThrow(RuntimeException::class);
});

it('talks to Microsoft Graph with an app token, sends only the title and time, and fails loudly', function () {
    $meeting = Meeting::factory()->online()->create(['agenda' => 'بند فيه اسم عضو']);
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token', 'expires_in' => 3600]),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events' => Http::response(teamsEvent($meeting, ['id' => 'event-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']])),
        'graph.microsoft.com/v1.0/users/organizer%40ejaz.example/events/event-1' => Http::sequence()->push([], 200)->push([], 204)->push(['error' => ['code' => 'ErrorItemNotFound']], 404),
    ]);
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example'));

    expect($graph->create($meeting))->toBe(['id' => 'event-1', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/1', 'organizer' => 'organizer@ejaz.example']);

    $meeting->forceFill(['online_meeting_id' => 'event-1'])->save();
    $graph->update($meeting);
    $graph->cancel($meeting);
    // الملغى أصلاً نجاح
    $graph->cancel($meeting);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth2/v2.0/token') && $request['grant_type'] === 'client_credentials' && $request['client_secret'] === 'secret-1');
    Http::assertSent(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/events')
        && $request->hasHeader('Authorization', 'Bearer graph-token')
        && $request['subject'] === $meeting->title && $request['isOnlineMeeting'] === true
        && ! array_key_exists('body', $request->data()) && ! array_key_exists('attendees', $request->data()));
    // مفتاح واحد للطلبات كلها
    Http::assertSentCount(5);
});

it('reads a Teams link that arrives late, and retries the same event so a retry never doubles it', function () {
    $meeting = Meeting::factory()->online()->create();
    Http::fake([
        'login.microsoftonline.com/*' => Http::response(['access_token' => 'graph-token', 'expires_in' => 3600]),
        'graph.microsoft.com/*' => Http::sequence()
            // الأول: الرابط يصل بقراءة الحدث بعد إنشائه
            ->push(teamsEvent($meeting, ['id' => 'event-1']))
            ->push(['id' => 'event-1', 'onlineMeeting' => ['joinUrl' => 'https://teams.microsoft.com/l/meetup-join/1']])
            // الثاني: لا رابط بعد القراءة، فيُعاد لاحقاً بالمعرّف نفسه
            ->push(teamsEvent($meeting, ['id' => 'event-2']))
            ->push(['id' => 'event-2', 'onlineMeeting' => null])
            // الثالث: تعذر البوابة
            ->push(['error' => 'down'], 503),
    ]);
    $graph = new GraphOnlineMeetings(new TeamsConnection('tenant-1', 'client-1', 'secret-1', 'organizer@ejaz.example'));

    expect($graph->create($meeting))->toBe(['id' => 'event-1', 'join_url' => 'https://teams.microsoft.com/l/meetup-join/1', 'organizer' => 'organizer@ejaz.example'])
        ->and(fn () => $graph->create($meeting))->toThrow(RuntimeException::class)
        ->and(fn () => $graph->create($meeting))->toThrow(Exception::class);

    // كل إنشاء للاجتماع بمعرّف منع التكرار نفسه، فيرده Graph إلى الحدث الأول
    $posts = Http::recorded(fn ($request) => $request->method() === 'POST' && str_ends_with($request->url(), '/events'));
    expect($posts)->toHaveCount(3)
        ->and(collect($posts)->map(fn ($pair) => $pair[0]['transactionId'])->unique()->values()->all())->toBe([$meeting->uuid.'-0']);
    Http::assertNotSent(fn ($request) => $request->method() === 'DELETE');
});

it('creates the Teams meeting again for an online meeting restored after deletion', function () {
    app()->instance(OnlineMeetings::class, $teams = new FakeOnlineMeetings);
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => true]);

    $meeting->fresh()->delete();
    $meeting->fresh()->restore();

    expect($teams->calls)->toBe(['create:'.$meeting->uuid, 'cancel:'.$meeting->uuid, 'create:'.$meeting->uuid])
        ->and($meeting->fresh()->online_join_url)->not->toBeNull();
});

it('keeps an attachment name safe for the download header', function () {
    $file = meetingAttachment();
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'attachments' => [['path' => $file['path'], 'name' => '../مجلد\\جدول.pdf']]]);

    expect($meeting->attachments[0]['name'])->toBe('..-مجلد-جدول.pdf');
});

it('marks a meeting held after it starts, and records attendance of active members with the quorum', function () {
    $meeting = Meeting::factory()->create(['starts_at' => now()->addDay(), 'quorum_percent' => 50]);
    $members = AssemblyMembership::factory()->count(5)->create()->pluck('user');
    $suspended = AssemblyMembership::factory()->status(MembershipStatus::Suspended)->create()->user;

    expect(fn () => app(MarkMeetingHeld::class)->handle($this->secretary, $meeting))->toThrow(AuthorizationException::class)
        ->and(fn () => app(RecordMeetingAttendance::class)->handle($this->secretary, $meeting, [$members[0]->id]))->toThrow(AuthorizationException::class);

    $meeting->forceFill(['starts_at' => now()->subHour()])->save();
    app(MarkMeetingHeld::class)->handle($this->secretary, $meeting);

    expect(fn () => app(RecordMeetingAttendance::class)->handle($this->secretary, $meeting->fresh(), [$members[0]->id, $suspended->id]))
        ->toThrow(ValidationException::class);

    app(RecordMeetingAttendance::class)->handle($this->secretary, $meeting->fresh(), [$members[0]->id, $members[1]->id]);
    $meeting->refresh();

    // خمسة أعضاء فعّالين ونسبة 50٪: يلزم ثلاثة
    expect($meeting->status)->toBe(MeetingStatus::Held)
        ->and($meeting->members_count)->toBe(5)
        ->and($meeting->attendees()->count())->toBe(2)
        ->and($meeting->quorumMet(2))->toBeFalse()
        ->and($meeting->quorumMet(3))->toBeTrue()
        ->and(Activity::where('event', 'attendance_recorded')->sole()->properties->all())->toBe(['present' => 2, 'members' => 5]);

    // تسجيل ثانٍ يستبدل الأول، والنصاب بلا نسبة لا يُحسب
    app(RecordMeetingAttendance::class)->handle($this->secretary, $meeting, [$members[2]->id]);
    expect($meeting->attendees()->pluck('users.id')->all())->toBe([$members[2]->id]);

    $meeting->forceFill(['quorum_percent' => null])->save();
    expect($meeting->quorumMet(5))->toBeNull();
});

it('keeps a board member to reading meetings, and keeps a held meeting from being deleted', function () {
    $board = userWithRole(RoleName::BoardMember);
    $held = Meeting::factory()->held()->create();

    expect($board->can('viewAny', Meeting::class))->toBeTrue()
        ->and($board->can('create', Meeting::class))->toBeFalse()
        ->and($board->can('update', $held))->toBeFalse()
        ->and($this->secretary->can('delete', $held))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('delete', $held))->toBeFalse()
        ->and($this->secretary->can('delete', Meeting::factory()->create()))->toBeTrue();
});
