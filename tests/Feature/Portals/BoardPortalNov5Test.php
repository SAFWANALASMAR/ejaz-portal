<?php

use App\Enums\MeetingBody;
use App\Enums\RoleName;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Filament\Widgets\RegistersOverviewWidget;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Models\LiveStream;
use App\Models\Meeting;
use App\Models\RegisterEntry;
use App\Models\User;

/*
| بوابة عضو مجلس الإدارة (ملاحظات المدير التنفيذي، ليلة 5 نوفمبر 2026، البند ٤ — docs/decisions/2026-11-06-board-portal.md):
| يدخل إلى «حسابي» كالمستفيد، وفيه صف بطاقات للمجلس وإحصائيات الموقع المجمّعة، بلا «المستندات ذات الصلاحية». وعضو الجمعية العمومية
| يرى الإحصائيات وحدها. وكل بطاقة إلى ما يُتاح لدوره بسياسته.
*/

const BP_PASSWORD = 'Thursday-Exam-Key-2026';

it('sends a board member to their account after login, and a board member with a staff role to the panel', function () {
    // بلا تحقق ثنائي مفعّل، فيصل الدخول إلى PortalLoginResponse مباشرة (وإتمام التحقق الثنائي يمر بالاستجابة نفسها):
    // يلزمه التحقق الثنائي فيبدأ في «الأمان» ليفعّله (المراجعة الأمنية على #197)
    $board = User::factory()->create(['email' => 'board@example.test', 'password' => BP_PASSWORD]);
    $board->assignRole(RoleName::BoardMember->value);
    $this->post('/login', ['login' => 'board@example.test', 'password' => BP_PASSWORD])->assertRedirect(route('account.security'));
    $this->post('/logout');

    $both = User::factory()->create(['email' => 'both@example.test', 'password' => BP_PASSWORD]);
    $both->assignRole(RoleName::BoardMember->value);
    $both->assignRole(RoleName::Secretary->value);
    $this->post('/login', ['login' => 'both@example.test', 'password' => BP_PASSWORD])->assertRedirect(url('/admin'));

    expect($board->fresh()->hasRole(RoleName::BoardMember->value))->toBeTrue();
});

it('shows the board the council cards and the site stats in their account, each card to what the role already opens', function () {
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();
    $board = userWithRole(RoleName::BoardMember);
    $meeting = Meeting::factory()->online()->create(['body' => MeetingBody::GeneralAssembly, 'starts_at' => now()->addDay(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/board']);
    showDeferredPortals();

    $this->actingAs($board)->get('/account')->assertOk()
        ->assertSee('data-council-cards', false)
        ->assertSee('data-council-card="stats"', false)
        ->assertSee('href="'.AssemblyMembershipResource::getUrl().'"', false)
        ->assertSee('href="'.route('assembly.index').'"', false)
        ->assertSee('href="'.route('assembly.meetings.show', $meeting).'" data-council-card="live"', false)
        ->assertSee('href="'.route('governance.index').'"', false)
        ->assertSee('id="site-stats"', false)
        ->assertSee(__('account.council.stats.visits'))
        ->assertSee(__('account.council.stats.book_reads'))
        ->assertSee(__('account.council.stats.media_views'));
});

it('sends the live card to the meetings page when no meeting is coming and nothing is streamed, never to a 404', function () {
    // قبول 6 نوفمبر: كانت إلى /live فترد 404 حين لا بث منشور
    $board = userWithRole(RoleName::BoardMember);

    $html = (string) $this->actingAs($board)->get('/account')->assertOk()->getContent();
    expect($html)->toContain('href="'.route('assembly.meetings.index').'" data-council-card="live"');
    $this->actingAs($board)->get(route('assembly.meetings.index'))->assertOk();
});

it('sends the live card to the live page when a stream is published and no meeting is coming', function () {
    LiveStream::factory()->create();
    $board = userWithRole(RoleName::BoardMember);

    expect((string) $this->actingAs($board)->get('/account')->getContent())
        ->toContain('href="'.route('live').'" data-council-card="live"');
    $this->actingAs($board)->get(route('live'))->assertOk();
});

it('shows a general assembly member the site stats only, and a beneficiary neither', function () {
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    $this->actingAs(userWithRole(RoleName::AssemblyMember))->get('/account')->assertOk()
        ->assertSee('id="site-stats"', false)
        ->assertDontSee('data-council-cards', false);

    $this->actingAs(userWithRole(RoleName::Member))->get('/account')->assertOk()
        ->assertDontSee('id="site-stats"', false)
        ->assertDontSee('data-council-cards', false);
});

it('keeps the site stats to aggregated numbers, with no member names', function () {
    $member = userWithRole(RoleName::AssemblyMember, ['name' => 'اسم عضو لا يظهر']);
    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    $this->actingAs(userWithRole(RoleName::BoardMember))->get('/account')->assertOk()
        ->assertSee('id="site-stats"', false)
        ->assertDontSee($member->name);
});

it('takes «documents with validity» away from the board: no widget, no list, no record', function () {
    $board = userWithRole(RoleName::BoardMember);
    $entry = RegisterEntry::factory()->create();

    expect($board->can('viewAny', RegisterEntry::class))->toBeFalse()
        ->and($board->can('view', $entry))->toBeFalse();

    $this->actingAs($board);
    expect(RegistersOverviewWidget::canView())->toBeFalse();
    $this->get(RegisterEntryResource::getUrl())->assertForbidden();

    // ويبقى للسكرتير كما كان
    $this->actingAs(userWithRole(RoleName::Secretary));
    expect(RegistersOverviewWidget::canView())->toBeTrue();
});

it('keeps the raw meeting link out of the account, links the live card to the board area or the assembly meeting page, and sends a board member without two-factor to security', function () {
    // المراجعة الأمنية على #197، العالية: «حسابي» بلا التحقق الثنائي، ورابط الاجتماع سرّ
    $board = userWithRole(RoleName::BoardMember);
    $meeting = Meeting::factory()->online()->create(['body' => MeetingBody::Board, 'starts_at' => now()->addDay(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/board-secret']);

    $html = (string) $this->actingAs($board)->get('/account')->assertOk()->getContent();

    // اجتماع المجلس في صفحة المنطقة (صفحة الاجتماع لاجتماع الجمعية العمومية وحده)، وتفتح له
    expect($html)->not->toContain('board-secret')
        ->toContain('href="'.route('assembly.index').'" data-council-card="live"');
    $this->actingAs($board)->get(route('assembly.index'))->assertOk()->assertSee('board-secret', false);

    $assemblyMeeting = Meeting::factory()->online()->create(['body' => MeetingBody::GeneralAssembly, 'starts_at' => now()->addHours(2), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/ga']);
    expect((string) $this->actingAs($board)->get('/account')->getContent())->toContain('href="'.route('assembly.meetings.show', $assemblyMeeting).'" data-council-card="live"');
    $this->actingAs($board)->get(route('assembly.meetings.show', $assemblyMeeting))->assertOk();

    // وعضو مجلس لم يفعّل التحقق الثنائي: لا رابط في «حسابي»، وصفحة الاجتماع تعيده إلى «الأمان»
    $without = User::factory()->create();
    $without->assignRole(RoleName::BoardMember->value);
    showDeferredPortals();
    expect((string) $this->actingAs($without)->get('/account')->assertOk()->getContent())->not->toContain('board-secret');
    $this->actingAs($without)->get(route('assembly.meetings.show', $assemblyMeeting))->assertRedirect(route('account.security'));
    // وصفحة المنطقة نفسها، وفيها روابط الاجتماعات وقرارات المجلس
    $this->actingAs($without)->get(route('assembly.index'))->assertRedirect(route('account.security'));
});

it('treats a session restored from «remember me» as unprotected: no meeting in the account, and the assembly area signs it out', function () {
    // فرع «تذكّرني» في EnsureTwoFactorIsEnabled::satisfied() (المشرف على #197، المنخفضة ١): عضو مفعّل التحقق الثنائي، وجلسته
    // استُعيدت من الكعكة بلا رمز، فلا يُعامل كمن أدخله
    $board = userWithRole(RoleName::BoardMember);
    showDeferredPortals();
    Meeting::factory()->online()->create(['body' => MeetingBody::Board, 'starts_at' => now()->addDay(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/board-secret']);
    $assemblyMeeting = Meeting::factory()->online()->create(['body' => MeetingBody::GeneralAssembly, 'starts_at' => now()->addHours(2), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/ga-secret']);

    $html = (string) $this->actingAs($board)->withSession([EnsureTwoFactorIsEnabled::VIA_REMEMBER => true])
        ->get('/account')->assertOk()->getContent();

    expect($board->hasEnabledTwoFactorAuthentication())->toBeTrue()
        ->and($html)->not->toContain('board-secret')->not->toContain('ga-secret')
        ->not->toContain(route('assembly.meetings.show', $assemblyMeeting))
        ->not->toContain('href="'.route('assembly.index').'" data-council-card="live"')
        // بلا اجتماع يُعرض ولا بث: إلى صفحة الاجتماعات كمن لا اجتماع له (#199)، وهي تخرجه إلى الدخول
        ->toContain('href="'.route('assembly.meetings.index').'" data-council-card="live"');

    $this->actingAs($board)->withSession([EnsureTwoFactorIsEnabled::VIA_REMEMBER => true])
        ->get(route('assembly.index'))->assertRedirect(route('login'));

    // ومع بث منشور: إلى صفحة البث العامة
    LiveStream::factory()->create();
    expect((string) $this->actingAs($board)->withSession([EnsureTwoFactorIsEnabled::VIA_REMEMBER => true])->get('/account')->getContent())
        ->not->toContain('board-secret')
        ->toContain('href="'.route('live').'" data-council-card="live"');
});
