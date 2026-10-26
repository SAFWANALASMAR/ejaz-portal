<?php

use App\Actions\Accounts\ExportUserData;
use App\Actions\Assembly\RecordMeetingAttendance;
use App\Actions\Assembly\SaveMeeting;
use App\Actions\Registers\SaveAgendaItem;
use App\Actions\Registers\SaveBodyMembership;
use App\Actions\Registers\SaveCommittee;
use App\Enums\AgendaItemStatus;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Models\AssemblyMembership;
use App\Models\BodyMembership;
use App\Models\Committee;
use App\Models\Meeting;
use App\Models\MeetingAgendaItem;
use App\Models\User;
use App\Notifications\MeetingNotice;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Activitylog\Models\Activity;

/*
| سجلات المجالس واللجان (requirements/13 §2) فوق وحدة الجمعية العمومية: الجهة على الاجتماع، وأعضاء المجلس
| واللجان بصفاتهم ومدة عضويتهم، وبنود الأعمال بقراراتها وتكليفاتها. كل جهة يطّلع عليها أعضاؤها، والسكرتير
| يدير، والمدير التنفيذي يطّلع على الكل.
*/

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->director = userWithRole(RoleName::ExecutiveDirector);
    $this->committee = Committee::factory()->create();
});

function bodyMeetingData(array $overrides = []): array
{
    return [
        'title' => 'اجتماع تجريبي',
        'type' => 'ordinary',
        'body' => 'general_assembly',
        'committee_id' => null,
        'starts_at' => now()->addWeek()->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i:s'),
        'location' => 'قاعة تجريبية',
        'is_online' => false,
        'attachments' => [],
        ...$overrides,
    ];
}

function bodyMember(MeetingBody $body, ?Committee $committee = null, array $attributes = []): User
{
    $user = $attributes['user'] ?? User::factory()->create();
    unset($attributes['user']);
    BodyMembership::factory()->create(['body' => $body, 'committee_id' => $committee?->id, 'user_id' => $user->id, ...$attributes]);

    return $user;
}

it('lets the secretary and the administrators manage committees and memberships, the super admin and the board read', function () {
    $committee = app(SaveCommittee::class)->handle($this->secretary, new Committee, ['name' => 'لجنة تجريبية', 'description' => null]);
    $membership = app(SaveBodyMembership::class)->handle($this->secretary, new BodyMembership, [
        'body' => 'committee', 'committee_id' => $committee->id, 'user_id' => User::factory()->create()->id,
        'role' => 'chair', 'starts_on' => '2026-01-01', 'ends_on' => '2029-12-31', 'notes' => null,
    ]);

    expect($membership->isActive())->toBeTrue()
        ->and(Activity::where('subject_type', BodyMembership::class)->where('event', 'created')->exists())->toBeTrue();

    foreach ([userWithRole(RoleName::SuperAdmin), userWithRole(RoleName::BoardMember)] as $reader) {
        expect($reader->can('viewAny', Committee::class))->toBeTrue()
            ->and($reader->can('view', $membership))->toBeTrue()
            ->and(fn () => app(SaveCommittee::class)->handle($reader, new Committee, ['name' => 'لجنة']))->toThrow(AuthorizationException::class);
    }

    foreach ([RoleName::ContentEditor, RoleName::ProjectsManager, RoleName::Member] as $role) {
        expect(userWithRole($role)->can('viewAny', BodyMembership::class))->toBeFalse();
    }
});

it('requires a committee for a committee membership only, and a valid term', function (array $override, string $field) {
    $data = ['body' => 'board', 'committee_id' => null, 'user_id' => User::factory()->create()->id, 'role' => 'member', 'starts_on' => '2026-01-01', 'ends_on' => null, ...$override];
    $data['committee_id'] = $data['committee_id'] === 'self' ? $this->committee->id : $data['committee_id'];

    expect(fn () => app(SaveBodyMembership::class)->handle($this->secretary, new BodyMembership, $data))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey($field));
})->with([
    'committee body without committee' => [['body' => 'committee'], 'committee_id'],
    'board with a committee' => [['committee_id' => 'self'], 'committee_id'],
    'assembly is not a membership body here' => [['body' => 'general_assembly'], 'body'],
    'end before start' => [['ends_on' => '2025-01-01'], 'ends_on'],
    'unknown role' => [['role' => 'king'], 'role'],
]);

it('enforces the committee rule in the database too', function () {
    expect(fn () => DB::table('body_memberships')->insert([
        'body' => 'board', 'committee_id' => $this->committee->id, 'user_id' => User::factory()->create()->id,
        'role' => 'member', 'starts_on' => '2026-01-01', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('counts a membership active within its term only', function () {
    $current = bodyMember(MeetingBody::Board, null, ['starts_on' => now()->subYear(), 'ends_on' => now()->addYear()]);
    bodyMember(MeetingBody::Board, null, ['starts_on' => now()->subYears(4), 'ends_on' => now()->subDays(2)]);
    bodyMember(MeetingBody::Board, null, ['starts_on' => now()->addDays(2), 'ends_on' => null]);
    $open = bodyMember(MeetingBody::Board, null, ['starts_on' => now()->subYear(), 'ends_on' => null]);

    expect(BodyMembership::activeUsers(MeetingBody::Board)->pluck('id')->sort()->values()->all())->toBe(collect([$current->id, $open->id])->sort()->values()->all());
});

it('saves the body on a meeting, with a committee for a committee meeting', function () {
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'committee', 'committee_id' => $this->committee->id]));

    expect($meeting->body)->toBe(MeetingBody::Committee)
        ->and($meeting->committee_id)->toBe($this->committee->id)
        ->and(Meeting::factory()->create()->body)->toBe(MeetingBody::GeneralAssembly);

    expect(fn () => app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'committee'])))->toThrow(ValidationException::class)
        ->and(fn () => app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'board', 'committee_id' => $this->committee->id])))->toThrow(ValidationException::class);

    $board = app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'board', 'committee_id' => null]));
    expect($board->committee_id)->toBeNull();
});

it('invites general assembly members to assembly meetings only', function () {
    $member = AssemblyMembership::factory()->create()->user;

    app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'board']));
    Notification::assertNotSentTo($member, MeetingNotice::class);

    app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData());
    Notification::assertSentTo($member, MeetingNotice::class);
});

it('records attendance from the active members of the meeting body', function () {
    $boardMember = bodyMember(MeetingBody::Board);
    $committeeMember = bodyMember(MeetingBody::Committee, $this->committee);
    $assemblyMember = AssemblyMembership::factory()->create()->user;

    $boardMeeting = Meeting::factory()->held()->create(['body' => MeetingBody::Board]);
    app(RecordMeetingAttendance::class)->handle($this->secretary, $boardMeeting, [$boardMember->id]);
    expect($boardMeeting->fresh()->members_count)->toBe(1);

    expect(fn () => app(RecordMeetingAttendance::class)->handle($this->secretary, $boardMeeting, [$assemblyMember->id]))->toThrow(ValidationException::class);

    $committeeMeeting = Meeting::factory()->held()->create(['body' => MeetingBody::Committee, 'committee_id' => $this->committee->id]);
    expect(fn () => app(RecordMeetingAttendance::class)->handle($this->secretary, $committeeMeeting, [$boardMember->id]))->toThrow(ValidationException::class);
    app(RecordMeetingAttendance::class)->handle($this->secretary, $committeeMeeting, [$committeeMember->id]);
    expect($committeeMeeting->attendees()->pluck('users.id')->all())->toBe([$committeeMember->id]);

    // واجتماع الجمعية العمومية كما كان
    $assemblyMeeting = Meeting::factory()->held()->create();
    app(RecordMeetingAttendance::class)->handle($this->secretary, $assemblyMeeting, [$assemblyMember->id]);
    expect(fn () => app(RecordMeetingAttendance::class)->handle($this->secretary, $assemblyMeeting, [$boardMember->id]))->toThrow(ValidationException::class);
});

it('shows each body to its members, everything to the secretary and the director', function () {
    $assembly = Meeting::factory()->create();
    $board = Meeting::factory()->create(['body' => MeetingBody::Board]);
    $committee = Meeting::factory()->create(['body' => MeetingBody::Committee, 'committee_id' => $this->committee->id]);
    $boardUser = userWithRole(RoleName::BoardMember);

    foreach ([$this->secretary, $this->director] as $all) {
        expect(Meeting::query()->visibleTo($all)->count())->toBe(3)
            ->and($all->can('view', $committee))->toBeTrue();
    }

    expect(Meeting::query()->visibleTo($boardUser)->pluck('id')->sort()->values()->all())->toBe(collect([$assembly->id, $board->id])->sort()->values()->all())
        ->and($boardUser->can('view', $committee))->toBeFalse();

    BodyMembership::factory()->create(['body' => MeetingBody::Committee, 'committee_id' => $this->committee->id, 'user_id' => $boardUser->id]);
    expect($boardUser->can('view', $committee))->toBeTrue()
        ->and(Meeting::query()->visibleTo($boardUser)->count())->toBe(3);

    expect(userWithRole(RoleName::SuperAdmin)->can('update', $assembly))->toBeFalse()
        ->and(userWithRole(RoleName::ContentEditor)->can('view', $assembly))->toBeFalse();
});

it('keeps board and committee meetings out of the members area', function () {
    $member = AssemblyMembership::factory()->create()->user;
    $board = Meeting::factory()->withApprovedMinutes()->create(['body' => MeetingBody::Board, 'title' => 'اجتماع المجلس السري']);
    $assembly = Meeting::factory()->withApprovedMinutes()->create(['title' => 'اجتماع الجمعية الظاهر']);

    $this->actingAs($member)->get(route('assembly.meetings.index'))->assertOk()->assertSee('اجتماع الجمعية الظاهر')->assertDontSee('اجتماع المجلس السري');
    $this->actingAs($member)->get(route('assembly.minutes.index'))->assertOk()->assertDontSee('اجتماع المجلس السري');
    $this->actingAs($member)->get(route('assembly.meetings.show', $board))->assertForbidden();
    $this->actingAs($member)->get(route('assembly.meetings.show', $assembly))->assertOk();
    $this->actingAs($member)->get($board->minutesUrl())->assertForbidden();
});

it('records agenda items with decisions, assignees, due dates and execution status', function () {
    $meeting = Meeting::factory()->held()->create(['body' => MeetingBody::Board]);
    $assignee = userWithRole(RoleName::ExecutiveDirector);

    $item = app(SaveAgendaItem::class)->handle($this->secretary, $meeting, new MeetingAgendaItem, [
        'position' => 1, 'title' => 'بند تجريبي', 'decision' => 'قرار تجريبي', 'assignee_id' => $assignee->id,
        'due_on' => '2026-12-01', 'status' => 'in_progress',
    ]);

    expect($item->status)->toBe(AgendaItemStatus::InProgress)
        ->and($item->meeting_id)->toBe($meeting->id)
        ->and(MeetingAgendaItem::query()->assignedTo($assignee)->pluck('id')->all())->toBe([$item->id])
        ->and(Activity::where('subject_type', MeetingAgendaItem::class)->where('event', 'created')->exists())->toBeTrue();

    app(SaveAgendaItem::class)->handle($this->secretary, $meeting, $item, ['position' => 1, 'title' => 'بند تجريبي', 'status' => 'completed']);
    expect($item->fresh()->status)->toBe(AgendaItemStatus::Completed);

    foreach ([userWithRole(RoleName::SuperAdmin), userWithRole(RoleName::BoardMember)] as $reader) {
        expect(fn () => app(SaveAgendaItem::class)->handle($reader, $meeting, new MeetingAgendaItem, ['position' => 2, 'title' => 'بند']))->toThrow(AuthorizationException::class)
            ->and($reader->can('view', $item))->toBeTrue();
    }

    expect(fn () => app(SaveAgendaItem::class)->handle($this->secretary, $meeting, new MeetingAgendaItem, ['position' => 2, 'title' => '', 'status' => 'done_and_dusted']))->toThrow(ValidationException::class);
});

it('hides the agenda items of a committee meeting from those who cannot see it', function () {
    $meeting = Meeting::factory()->create(['body' => MeetingBody::Committee, 'committee_id' => $this->committee->id]);
    $item = MeetingAgendaItem::factory()->create(['meeting_id' => $meeting->id]);

    expect(userWithRole(RoleName::BoardMember)->can('view', $item))->toBeFalse()
        ->and($this->director->can('view', $item))->toBeTrue();
});

it('keeps the body of a meeting fixed after it is created', function () {
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'board']));

    expect(fn () => app(SaveMeeting::class)->handle($this->secretary, $meeting, bodyMeetingData(['body' => 'general_assembly'])))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('body'));

    // وبلا جهة في البيانات تبقى جهته، كتعديل النموذج والحقل معطّل
    $data = bodyMeetingData(['title' => 'عنوان معدّل']);
    unset($data['body'], $data['committee_id']);
    app(SaveMeeting::class)->handle($this->secretary, $meeting, $data);
    expect($meeting->fresh()->body)->toBe(MeetingBody::Board)->and($meeting->fresh()->title)->toBe('عنوان معدّل');

    $committeeMeeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, bodyMeetingData(['body' => 'committee', 'committee_id' => $this->committee->id]));
    expect(fn () => app(SaveMeeting::class)->handle($this->secretary, $committeeMeeting, bodyMeetingData(['body' => 'committee', 'committee_id' => Committee::factory()->create()->id])))->toThrow(ValidationException::class);
});

it('only assigns decisions to active accounts that see the meetings, and only adds active members', function () {
    $meeting = Meeting::factory()->held()->create(['body' => MeetingBody::Board]);

    expect(fn () => app(SaveAgendaItem::class)->handle($this->secretary, $meeting, new MeetingAgendaItem, ['position' => 1, 'title' => 'بند', 'assignee_id' => userWithRole(RoleName::Member)->id]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('assignee_id'));

    expect(fn () => app(SaveBodyMembership::class)->handle($this->secretary, new BodyMembership, [
        'body' => 'board', 'user_id' => User::factory()->create(['is_active' => false])->id, 'role' => 'member', 'starts_on' => '2026-01-01',
    ]))->toThrow(fn (ValidationException $exception) => expect($exception->errors())->toHaveKey('user_id'));
});

it('keeps a deleted agenda item restorable rather than gone', function () {
    $item = MeetingAgendaItem::factory()->create();
    $item->delete();

    expect(MeetingAgendaItem::withTrashed()->find($item->id))->not->toBeNull();
});

it('includes the council and committee memberships and the assigned decisions in the personal data copy, and not those of others', function () {
    $user = User::factory()->create();
    $committee = Committee::factory()->create(['name' => 'لجنة تجريبية']);
    BodyMembership::factory()->for($user)->create(['starts_on' => '2026-01-01']);
    BodyMembership::factory()->for($user)->create(['body' => MeetingBody::Committee, 'committee_id' => $committee->id, 'starts_on' => '2026-02-01', 'ends_on' => '2026-12-31']);
    BodyMembership::factory()->create();
    $item = MeetingAgendaItem::factory()->create(['title' => 'بند تجريبي', 'decision' => 'قرار تجريبي', 'assignee_id' => $user->id, 'due_on' => '2026-11-01']);
    MeetingAgendaItem::factory()->create(['assignee_id' => User::factory()]);

    $copy = app(ExportUserData::class)->handle($user);

    expect($copy['body_memberships'])->toBe([
        ['body' => 'board', 'committee' => null, 'role' => 'member', 'starts_on' => '2026-01-01', 'ends_on' => null],
        ['body' => 'committee', 'committee' => 'لجنة تجريبية', 'role' => 'member', 'starts_on' => '2026-02-01', 'ends_on' => '2026-12-31'],
    ])->and($copy['assigned_decisions'])->toBe([
        ['meeting' => $item->meeting->title, 'item' => 'بند تجريبي', 'due_on' => '2026-11-01', 'status' => 'not_started'],
    ]);

    // البند المحذوف لا يُصدَّر، وحضور المجلس في قسمه لا تحت عضوية الجمعية العمومية
    $item->delete();
    $board = Meeting::factory()->create(['body' => MeetingBody::Board]);
    $board->attendees()->attach($user->id);

    $copy = app(ExportUserData::class)->handle($user);

    expect($copy['assigned_decisions'])->toBe([])
        ->and($copy['body_meetings_attended'])->toHaveCount(1)
        ->and($copy['body_meetings_attended'][0]['body'])->toBe('board');
});
