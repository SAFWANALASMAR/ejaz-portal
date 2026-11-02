<?php

use App\Actions\Assembly\SaveMeeting;
use App\Contracts\OnlineMeetings;
use App\Enums\ActivityEvent;
use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\Meetings\Pages\CreateMeeting;
use App\Filament\Resources\Meetings\Pages\EditMeeting;
use App\Jobs\SyncOnlineMeeting;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Notifications\MeetingNotice;
use App\Rules\MeetingJoinUrl;
use App\Services\OnlineMeetings\FakeOnlineMeetings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;

/*
| رابط الاجتماع اليدوي (docs/decisions/2026-11-03-meeting-manual-link.md): يلصقه السكرتير من تيمز أو زووم
| أو ميت، فيلزم إن كان ربط تيمز مطفأً ويغلبه إن كان يعمل، ويُحفظ مشفّراً في رابط الاجتماع نفسه، ولا يظهر
| إلا للأعضاء بعد الدخول.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->secretary = userWithRole(RoleName::Secretary);
    $this->data = [
        'title' => 'اجتماع تجريبي برابط يدوي',
        'type' => 'ordinary',
        'starts_at' => now()->addWeeks(2)->format('Y-m-d H:i:s'),
        'ends_at' => now()->addWeeks(2)->addHours(2)->format('Y-m-d H:i:s'),
        'location' => null,
        'is_online' => true,
        'agenda' => null,
        'attachments' => [],
    ];
});

/**
 * ربط تيمز مطفأ: Graph بلا إعداد مفعّل، ولا طلب يخرج.
 */
function teamsSwitchedOff(): void
{
    config(['services.online_meetings.driver' => 'graph']);
    Http::preventStrayRequests();
}

it('accepts a link from each approved domain', function (string $url) {
    expect(MeetingJoinUrl::isValid($url))->toBeTrue();

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => $url])->fresh();

    expect($meeting->online_join_url)->toBe($url)
        ->and($meeting->online_join_manual)->toBeTrue()
        // مشفّر في القاعدة كرابط تيمز الآلي
        ->and(DB::table('meetings')->where('id', $meeting->id)->value('online_join_url'))->not->toContain(parse_url($url, PHP_URL_HOST));
})->with([
    'teams' => 'https://teams.microsoft.com/l/meetup-join/19%3ameeting_abc%40thread.v2/0?context=%7b%7d',
    'teams live' => 'https://teams.live.com/meet/9876543210',
    'zoom' => 'https://zoom.us/j/1234567890?pwd=abc',
    'zoom subdomain' => 'https://us02web.zoom.us/j/1234567890',
    'google meet' => 'https://meet.google.com/abc-defg-hij',
]);

it('refuses other domains, look-alikes, userinfo, http and links without a host', function (string $url) {
    expect(MeetingJoinUrl::isValid($url))->toBeFalse()
        ->and(fn () => app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => $url]))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors()['manual_join_url'][0])->toBe(__('validation.meeting_join_url')));

    expect(Meeting::count())->toBe(0);
})->with([
    'other domain' => 'https://evil.example/j/123',
    'look-alike suffix' => 'https://zoom.us.evil.com/j/123',
    'look-alike prefix' => 'https://evilzoom.us/j/123',
    'look-alike teams' => 'https://teams.microsoft.com.evil.com/l/meetup-join/1',
    'userinfo' => 'https://user@teams.microsoft.com/l/meetup-join/1',
    'userinfo with password' => 'https://a:b@meet.google.com/abc',
    'http' => 'http://teams.microsoft.com/l/meetup-join/1',
    'javascript' => 'javascript:alert(1)',
    'no host' => 'https:///l/meetup-join/1',
    'relative' => '/l/meetup-join/1',
    'port' => 'https://zoom.us:8443/j/1',
    'backslash' => 'https://evil.example\\@zoom.us/j/1',
    'host in fragment' => 'https://evil.com#@zoom.us/j/1',
    'trailing dot' => 'https://zoom.us./j/1',
    'encoded dot' => 'https://evil%2ecom.zoom.us/j/1',
    'fullwidth dot' => 'https://zoom．us/j/1',
    'encoded newline' => 'https://zoom.us/j/1%0aX',
    'nested zoom subdomain' => 'https://a.b.zoom.us/j/1',
    'meet redirect' => 'https://meet.google.com/linkredirect?dest=https://phish.example',
]);

it('requires the link while Teams is switched off, and leaves it optional while it is on', function () {
    teamsSwitchedOff();

    expect(fn () => app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data))
        ->toThrow(fn (ValidationException $exception) => expect($exception->errors()['manual_join_url'][0])->toBe(__('assembly.validation.manual_link_required')));

    // في القاعة لا يلزم رابط
    app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => false, 'location' => 'قاعة تجريبية']);

    $manual = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => 'https://meet.google.com/abc-defg-hij'])->fresh();
    expect($manual->online_join_url)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($manual->online_sync_error)->toBeNull()
        ->and($manual->needsOnlineSync())->toBeFalse();

    // والربط يعمل (الوهمي): بلا رابط يُنشأ رابط تيمز آلياً
    config(['services.online_meetings.driver' => 'fake']);
    app()->instance(OnlineMeetings::class, new FakeOnlineMeetings);
    $automatic = app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data)->fresh();

    expect($automatic->online_join_url)->toBe('https://teams.example.test/meet/'.$automatic->uuid)
        ->and($automatic->online_join_manual)->toBeFalse();
});

it('applies the same rule in the panel form', function () {
    $this->actingAs($this->secretary);
    $form = [
        'title' => 'اجتماع من اللوحة',
        'body' => MeetingBody::GeneralAssembly->value,
        'type' => 'ordinary',
        'starts_at' => now()->addWeek()->format('Y-m-d H:i'),
        'ends_at' => now()->addWeek()->addHours(2)->format('Y-m-d H:i'),
        'is_online' => true,
    ];

    teamsSwitchedOff();
    Livewire::test(CreateMeeting::class)->fillForm($form)->call('create')->assertHasFormErrors(['manual_join_url' => 'required']);
    Livewire::test(CreateMeeting::class)->fillForm([...$form, 'manual_join_url' => 'https://zoom.us.evil.com/j/1'])->call('create')->assertHasFormErrors(['manual_join_url']);
    Livewire::test(CreateMeeting::class)->fillForm([...$form, 'manual_join_url' => 'https://zoom.us/j/1'])->call('create')->assertHasNoFormErrors();

    $meeting = Meeting::sole();
    expect($meeting->online_join_url)->toBe('https://zoom.us/j/1');

    // التعديل يعرض الرابط اليدوي، ويستبدله
    Livewire::test(EditMeeting::class, ['record' => $meeting->uuid])
        ->assertFormSet(['manual_join_url' => 'https://zoom.us/j/1'])
        ->fillForm(['manual_join_url' => 'https://meet.google.com/abc-defg-hij'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($meeting->fresh()->online_join_url)->toBe('https://meet.google.com/abc-defg-hij');

    // والربط يعمل: الحقل اختياري
    config(['services.online_meetings.driver' => 'fake']);
    Livewire::test(CreateMeeting::class)->fillForm([...$form, 'title' => 'اجتماع آلي'])->call('create')->assertHasNoFormErrors();
});

it('lets the manual link win over Teams: no Teams meeting is created, and an existing one is cancelled', function () {
    app()->instance(OnlineMeetings::class, $teams = new FakeOnlineMeetings);

    $manual = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => 'https://zoom.us/j/1'])->fresh();

    expect($teams->calls)->toBe([])
        ->and($manual->online_meeting_id)->toBeNull()
        ->and($manual->online_join_url)->toBe('https://zoom.us/j/1');

    // تعديل العنوان والموعد لا يمس تيمز
    app(SaveMeeting::class)->handle($this->secretary, $manual, [...$this->data, 'title' => 'عنوان آخر', 'manual_join_url' => 'https://zoom.us/j/1']);
    expect($teams->calls)->toBe([]);

    // اجتماع آلي ثم لُصق له رابط: يُلغى اجتماع تيمز ويبقى الرابط اليدوي
    $automatic = app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data)->fresh();
    app(SaveMeeting::class)->handle($this->secretary, $automatic, [...$this->data, 'manual_join_url' => 'https://meet.google.com/abc-defg-hij']);
    $automatic->refresh();

    expect($teams->calls)->toBe(['create:'.$automatic->uuid, 'cancel:'.$automatic->uuid])
        ->and($automatic->online_meeting_id)->toBeNull()
        ->and($automatic->online_join_url)->toBe('https://meet.google.com/abc-defg-hij')
        ->and($automatic->needsOnlineSync())->toBeFalse();

    // وإزالة الرابط اليدوي ترده إلى الربط فيُنشأ له رابط تيمز
    app(SaveMeeting::class)->handle($this->secretary, $automatic, [...$this->data, 'manual_join_url' => null]);
    $automatic->refresh();

    expect($automatic->online_join_manual)->toBeFalse()
        ->and($automatic->online_join_url)->toBe('https://teams.example.test/meet/'.$automatic->uuid)
        ->and(array_slice($teams->calls, -1))->toBe(['create:'.$automatic->uuid]);

    // والاجتماع في القاعة لا يبقى له رابط يدوي
    app(SaveMeeting::class)->handle($this->secretary, $manual->fresh(), [...$this->data, 'is_online' => false, 'location' => 'قاعة', 'manual_join_url' => 'https://zoom.us/j/1']);
    expect($manual->fresh()->online_join_url)->toBeNull()->and($manual->fresh()->online_join_manual)->toBeFalse();
});

it('shows the manual link to members only, after login', function () {
    $member = AssemblyMembership::factory()->create()->user;
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => 'https://zoom.us/j/555666777'])->fresh();

    $this->get(route('assembly.meetings.show', $meeting))->assertRedirect();
    $this->get('/assembly')->assertDontSee('555666777');

    $this->actingAs(userWithRole(RoleName::Member))->get(route('assembly.meetings.show', $meeting))->assertForbidden();

    $this->actingAs($member)->get(route('assembly.meetings.show', $meeting))->assertOk()
        ->assertSee('https://zoom.us/j/555666777')
        ->assertSee(__('assembly.meetings.join_manual'));
});

it('invites members by email to the meeting page, where the manual link waits after login', function () {
    Notification::fake();
    $member = AssemblyMembership::factory()->create()->user;

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => 'https://zoom.us/j/555666777']);

    Notification::assertSentTo($member, MeetingNotice::class, fn (MeetingNotice $notice) => $notice->kind === 'scheduled' && $notice->meeting->is($meeting));

    // كرابط تيمز الآلي: البريد يدعو إلى صفحة الاجتماع، والرابط نفسه لا يخرج في البريد (قرار 9 أكتوبر)
    $mail = (string) (new MeetingNotice($meeting->fresh(), 'scheduled'))->toMail($member)->render();
    expect($mail)->toContain(route('assembly.meetings.show', $meeting))
        ->toContain(e(__('assembly.mail.online')))
        ->not->toContain('555666777');
});

it('records setting, changing and removing the manual link, with its host only', function () {
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => 'https://zoom.us/j/555666777']);
    app(SaveMeeting::class)->handle($this->secretary, $meeting->fresh(), [...$this->data, 'manual_join_url' => 'https://zoom.us/j/555666777']);
    app(SaveMeeting::class)->handle($this->secretary, $meeting->fresh(), [...$this->data, 'manual_join_url' => 'https://meet.google.com/abc-defg-hij']);
    app(SaveMeeting::class)->handle($this->secretary, $meeting->fresh(), [...$this->data, 'manual_join_url' => null]);

    $logs = Activity::where('event', ActivityEvent::MeetingJoinLinkChanged->value)->where('subject_id', $meeting->id)->orderBy('id')->get();

    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('causer_id')->unique()->all())->toBe([$this->secretary->id])
        ->and($logs->map(fn (Activity $log) => $log->properties['host'])->all())->toBe(['zoom.us', 'meet.google.com', null])
        ->and($logs->last()->properties['removed'])->toBeTrue()
        // الرابط نفسه سر لا يدخل السجل
        ->and(Activity::query()->get()->toJson())->not->toContain('555666777')->not->toContain('abc-defg-hij')
        ->and(__('activity.events.'.ActivityEvent::MeetingJoinLinkChanged->value))->toBe('تعديل رابط الاجتماع اليدوي');
});

it('trims a pasted link, accepts upper-case scheme and host, and ignores the field for a meeting in a hall', function () {
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'manual_join_url' => "  https://zoom.us/j/1\n"])->fresh();
    expect($meeting->online_join_url)->toBe('https://zoom.us/j/1')
        ->and(MeetingJoinUrl::isValid('HTTPS://ZOOM.US/j/1'))->toBeTrue();

    $hall = app(SaveMeeting::class)->handle($this->secretary, new Meeting, [...$this->data, 'is_online' => false, 'location' => 'قاعة', 'manual_join_url' => 'not a link'])->fresh();
    expect($hall->online_join_url)->toBeNull();
});

it('lets a meeting that already has a Teams meeting be edited without a link after Teams is switched off', function () {
    app()->instance(OnlineMeetings::class, new FakeOnlineMeetings);
    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data)->fresh();
    expect($meeting->online_meeting_id)->not->toBeNull();

    teamsSwitchedOff();
    app(SaveMeeting::class)->handle($this->secretary, $meeting, [...$this->data, 'agenda' => 'بند مضاف']);

    expect($meeting->fresh()->agenda)->toBe('بند مضاف')
        ->and(SaveMeeting::manualLinkRequired($meeting->fresh(), true))->toBeFalse()
        ->and(SaveMeeting::manualLinkRequired(new Meeting, true))->toBeTrue();

    // وكذلك في نموذج اللوحة
    $this->actingAs($this->secretary);
    Livewire::test(EditMeeting::class, ['record' => $meeting->uuid])
        ->fillForm(['title' => 'عنوان معدّل'])
        ->call('save')
        ->assertHasNoFormErrors();
    expect($meeting->fresh()->title)->toBe('عنوان معدّل');
});

it('keeps a manual link pasted while a Teams meeting is being created, and cancels that Teams meeting', function () {
    // يلصق السكرتير الرابط أثناء طلب الإنشاء إلى Microsoft
    $teams = new class implements OnlineMeetings
    {
        /** @var list<string> */
        public array $calls = [];

        public function create(Meeting $meeting): array
        {
            $this->calls[] = 'create';
            Meeting::whereKey($meeting->id)->sole()->forceFill(['online_join_url' => 'https://zoom.us/j/42', 'online_join_manual' => true])->saveQuietly();

            return ['id' => 'teams-1', 'join_url' => 'https://teams.example.test/meet/1', 'organizer' => null];
        }

        public function update(Meeting $meeting): void
        {
            $this->calls[] = 'update';
        }

        public function cancel(Meeting $meeting): void
        {
            $this->calls[] = 'cancel';
        }
    };
    app()->instance(OnlineMeetings::class, $teams);

    $meeting = app(SaveMeeting::class)->handle($this->secretary, new Meeting, $this->data);
    expect($meeting->fresh()->online_join_url)->toBe('https://zoom.us/j/42');

    // والمهمة التي يطلقها حفظ الرابط تلغي اجتماع تيمز وتُبقي الرابط
    (new SyncOnlineMeeting($meeting->id))->handle();
    $meeting->refresh();

    expect($teams->calls)->toBe(['create', 'cancel'])
        ->and($meeting->online_meeting_id)->toBeNull()
        ->and($meeting->online_join_url)->toBe('https://zoom.us/j/42');
});
