<?php

use App\Enums\MembershipStatus;
use App\Enums\RoleName;
use App\Models\AssemblyFile;
use App\Models\AssemblyMembership;
use App\Models\Meeting;
use App\Models\User;
use App\Support\AccountNavigation;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Activitylog\Models\Activity;

/*
| منطقة الجمعية العمومية في الموقع (requirements/04): غير الأعضاء لا يصلون لأي جزء منها، والملفات خاصة
| تُنزَّل بروابط مؤقتة ويُسجَّل تنزيلها.
*/

beforeEach(function () {
    Storage::fake('local');
    $this->member = AssemblyMembership::factory()->create()->user;
    $this->meeting = Meeting::factory()->withApprovedMinutes()->create(['attachments' => [['path' => Meeting::ATTACHMENTS_DIRECTORY.'/a.pdf', 'name' => 'جدول.pdf', 'size' => 10]]]);
    $this->file = AssemblyFile::factory()->create();
    $this->boardFile = AssemblyFile::factory()->forBoard()->create();

    foreach ([$this->meeting->minutes_file, Meeting::ATTACHMENTS_DIRECTORY.'/a.pdf', $this->file->file, $this->boardFile->file] as $path) {
        Storage::disk('local')->put((string) $path, pdfContents());
    }
});

/**
 * كل مسارات المنطقة الخاصة، بروابطها الموقّعة للتنزيل.
 *
 * @return list<string>
 */
function assemblyAreaUrls(Meeting $meeting, AssemblyFile $file): array
{
    return [
        route('assembly.meetings.index'),
        route('assembly.meetings.show', $meeting),
        route('assembly.minutes.index'),
        route('assembly.files.index'),
        URL::temporarySignedRoute('assembly.meetings.attachment', now()->addMinutes(10), [$meeting, 0]),
        URL::temporarySignedRoute('assembly.meetings.minutes', now()->addMinutes(10), [$meeting]),
        URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(10), [$file]),
    ];
}

it('sends guests to log in from every private page of the area', function () {
    foreach (assemblyAreaUrls($this->meeting, $this->file) as $url) {
        $this->get($url)->assertRedirect(route('login'));
    }
});

it('refuses every account that is not in the area, a suspended member included', function (Closure $account) {
    $user = $account();

    foreach (assemblyAreaUrls($this->meeting, $this->file) as $url) {
        $this->actingAs($user)->get($url)->assertForbidden();
    }
})->with([
    'beneficiary' => fn () => User::factory()->create(),
    'suspended member' => fn () => AssemblyMembership::factory()->status(MembershipStatus::Suspended)->create()->user,
    'content editor' => fn () => userWithRole(RoleName::ContentEditor),
    'program manager' => fn () => userWithRole(RoleName::ProgramManager),
]);

it('opens the area to members, the board, the secretary and the super admin, and keeps it out of caches', function (Closure $account) {
    $user = $account() ?? $this->member;

    foreach (array_slice(assemblyAreaUrls($this->meeting, $this->file), 0, 4) as $url) {
        $this->actingAs($user)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
    }
})->with([
    'member' => fn () => null,
    'board member' => fn () => userWithRole(RoleName::BoardMember),
    'secretary' => fn () => userWithRole(RoleName::Secretary),
    'super admin' => fn () => userWithRole(RoleName::TechnicalAdmin),
]);

it('shows the section page to everyone, with the tiles fitting who looks', function () {
    // الزائر: البلاطات الأربع كما في الصورة 05
    $this->get('/assembly')->assertOk()->assertSee(__('assembly.title'))
        ->assertSeeInOrder([__('assembly.index.login'), __('assembly.index.meetings'), __('assembly.index.minutes'), __('assembly.index.files')]);

    // صاحب حساب غير عضو: تنبيه ورابط طلب الانضمام، بلا بلاطات المنطقة
    $this->actingAs(User::factory()->create())->get('/assembly')->assertOk()
        ->assertSee(__('assembly.index.not_member'))->assertSee(route('services.assembly-membership'))
        ->assertDontSee(route('assembly.meetings.index'));

    // العضو: البلاطات الثلاث والاجتماع القادم، بلا «دخول الأعضاء»
    $upcoming = Meeting::factory()->create(['title' => 'اجتماع قادم تجريبي']);
    $this->actingAs($this->member)->get('/assembly')->assertOk()
        ->assertSee(route('assembly.meetings.index'))->assertSee('اجتماع قادم تجريبي')
        ->assertDontSee(__('assembly.index.login'));
});

it('lists the meetings and shows a meeting with its agenda, attachments, attendance and approved minutes', function () {
    $this->actingAs($this->member)->get(route('assembly.meetings.index'))->assertOk()
        ->assertSee($this->meeting->title)->assertSee(__('assembly.meetings.details'));

    $this->get(route('assembly.meetings.show', $this->meeting))->assertOk()
        ->assertSee('بند تجريبي أول')
        ->assertSee('جدول.pdf')
        ->assertSee(__('assembly.meetings.minutes_download'));

    // المسودة لا تظهر للأعضاء، ولا رابط لها
    $draft = Meeting::factory()->withDraftMinutes()->create();
    $this->get(route('assembly.meetings.show', $draft))->assertOk()
        ->assertSee(__('assembly.meetings.minutes_pending'))->assertDontSee(__('assembly.meetings.minutes_download'));
    $this->get(URL::temporarySignedRoute('assembly.meetings.minutes', now()->addMinutes(10), [$draft]))->assertForbidden();

    $this->get(route('assembly.minutes.index'))->assertOk()->assertSee($this->meeting->title)->assertDontSee($draft->title);
});

it('shows the Teams link to members only, and not once the meeting is cancelled', function () {
    $online = Meeting::factory()->online()->create();
    $online->forceFill(['online_join_url' => 'https://teams.microsoft.com/l/meetup-join/secret'])->save();

    $this->get('/assembly')->assertDontSee('meetup-join/secret');
    $this->actingAs($this->member)->get(route('assembly.meetings.show', $online))->assertOk()->assertSee('meetup-join/secret');

    $online->forceFill(['status' => 'cancelled'])->save();
    $this->get(route('assembly.meetings.show', $online))->assertOk()
        ->assertSee(__('assembly.meetings.cancelled_notice'))->assertDontSee('meetup-join/secret');
});

it('hides board files from members, and serves files only by a valid signed link, logging each download', function () {
    $this->actingAs($this->member)->get(route('assembly.files.index'))->assertOk()
        ->assertSee($this->file->title)->assertDontSee($this->boardFile->title);

    $this->get(URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(10), [$this->boardFile]))->assertForbidden();
    $this->get(route('assembly.files.download', $this->file))->assertForbidden();
    $this->get(URL::temporarySignedRoute('assembly.files.download', now()->subMinute(), [$this->file]))->assertForbidden();

    $this->get(URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(10), [$this->file]))->assertOk()->assertDownload();
    // الاسم العربي في filename* (UTF-8)، وفي filename بديله اللاتيني للمتصفحات القديمة
    $attachment = $this->get(URL::temporarySignedRoute('assembly.meetings.attachment', now()->addMinutes(10), [$this->meeting, 0]))->assertOk()->assertDownload();
    expect($attachment->headers->get('Content-Disposition'))->toContain("filename*=utf-8''".rawurlencode('جدول.pdf'));
    $this->get(URL::temporarySignedRoute('assembly.meetings.attachment', now()->addMinutes(10), [$this->meeting, 5]))->assertNotFound();
    $this->get(URL::temporarySignedRoute('assembly.meetings.minutes', now()->addMinutes(10), [$this->meeting]))->assertOk()->assertDownload();

    $logs = Activity::where('event', 'file_downloaded')->orderBy('id')->get();
    expect($logs)->toHaveCount(3)
        ->and($logs->pluck('causer_id')->unique()->all())->toBe([$this->member->id])
        ->and($logs->pluck('properties.file')->all())->toBe(['file', 'attachment:0', 'minutes']);

    // ومجلس الإدارة يرى ملف المجلس وينزله
    $this->actingAs(userWithRole(RoleName::BoardMember))->get(route('assembly.files.index'))->assertSee($this->boardFile->title);
    $this->get(URL::temporarySignedRoute('assembly.files.download', now()->addMinutes(10), [$this->boardFile]))->assertOk();
});

it('puts the assembly in the account menu and cards only for those who reach it, in the order of image 04', function () {
    // مؤجلة بقرار المدير التنفيذي حتى تُظهر من اللوحة (AccountSettings)
    showDeferredPortals();
    expect(AccountNavigation::for(User::factory()->create())->pluck('route')->all())->not->toContain('assembly.index');

    expect(AccountNavigation::for($this->member)->pluck('route')->all())
        ->toBe(['account.dashboard', 'account.profile', 'account.programs.index', 'account.requests.index', 'assembly.index', 'account.my-reading', 'account.competitions'])
        ->and(AccountNavigation::cards($this->member)->pluck('route')->all())
        ->toBe(['account.programs.index', 'account.profile', 'assembly.index', 'account.requests.index', 'account.competitions']);
});

it('opens every members page with the shared page hero and the big calligraphy under one title (director note 14)', function () {
    $pages = [
        route('assembly.meetings.index') => __('assembly.meetings.title'),
        route('assembly.meetings.show', $this->meeting) => $this->meeting->title,
        route('assembly.minutes.index') => __('assembly.minutes.title'),
        route('assembly.files.index') => __('assembly.files.title'),
    ];

    foreach ($pages as $url => $title) {
        $html = $this->actingAs($this->member)->get($url)->assertOk()->getContent();

        expect($html)->toContain('data-page-hero')->toContain('data-page-calligraphy')
            ->and(preg_match_all('/<h1[\s>]/', $html))->toBe(1)
            ->and($html)->toMatch('/<h1[^>]*>'.preg_quote(e($title), '/').'<\/h1>/');
    }

    // والعودة من الاجتماع شريط الرأس وحده إلى «الاجتماعات»، لا رابطان متتاليان إلى الوجهة نفسها
    $show = $this->actingAs($this->member)->get(route('assembly.meetings.show', $this->meeting))->getContent();
    expect(substr_count($show, 'href="'.route('assembly.meetings.index').'"'))->toBe(1);
});
