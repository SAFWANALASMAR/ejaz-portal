<?php

use App\Actions\Library\SaveLibraryItem;
use App\Actions\Settings\UpdateHomeContent;
use App\Enums\AccountCardAudience;
use App\Enums\AnnouncementKind;
use App\Enums\LibrarySection;
use App\Enums\MeetingBody;
use App\Enums\MeetingStatus;
use App\Enums\RoleName;
use App\Enums\SiteIcon;
use App\Models\AccountCard;
use App\Models\Announcement;
use App\Models\LibraryItem;
use App\Models\Meeting;
use App\Models\NewsArticle;
use App\Models\Setting;
use App\Models\User;
use App\Support\AccountNavigation;
use App\Support\AccountSettings;
use App\Support\HomeContent;
use App\Support\NationalAddress;
use App\Support\Portals;
use App\Support\PortalStats;
use Database\Seeders\MenuSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/*
| الرئيسية بترتيب المدير التنفيذي، والإعلانات، والقوائم ومكتبة إعجاز بروابط يوتيوب، وبطاقات لوحة المستفيد، والبوابات المؤجلة،
| ورئيسية المشرف العام (ملاحظات المدير التنفيذي عبر صفوان، 25 أكتوبر 2026).
*/

it('shows the home sections in the director\'s order with the strategic plan texts verbatim', function () {
    NewsArticle::factory()->create(['title' => 'خبر الشريط']);
    Announcement::factory()->create(['title' => 'إعلان الشريط']);

    $html = $this->get('/')->assertOk()->getContent();

    // ترتيب التصور النهائي، النسخة 63 (§٢ب): البطاقات، ثم الخدمات، ثم الإعلانات والأخبار متجاورتين. وكلمتا خادم الحرمين
    // الشريفين ورئيس الجمعية شريحتان في البطل، فلا مقطع لهما ولا للمبررات ولا لقنوات التواصل (الحسابات في التذييل)
    $order = ['home-identity', 'home-shortcuts', 'home-announcements', 'home-news'];
    $positions = array_map(fn (string $id): int|false => strpos($html, 'id="'.$id.'"'), $order);
    expect($positions)->not->toContain(false)->and($positions)->toBe(collect($positions)->sort()->values()->all())
        ->and($html)->not->toContain('id="home-king"')
        ->and($html)->not->toContain('id="home-chairman"')
        ->and($html)->not->toContain('id="home-rationale"')
        ->and($html)->not->toContain('aria-labelledby="home-accounts"');

    $this->get('/')
        ->assertSee(__('home.sections.king.text'))
        ->assertSee('الملك سلمان بن عبدالعزيز آل سعود')
        ->assertSee('رئيس مجلس إدارة جمعية إعجاز للدراسات والبحوث العلمية والتعليمية')
        ->assertSee('تبني الابتكار المؤدي إلى التميز لإيجاد بيئة جاذبة ومحفزة للإبداع.')
        ->assertSee('خبر الشريط')
        ->assertSee('إعلان الشريط')
        ->assertDontSee('id="home-stats"', false);
});

it('replaces any home text and image from the panel, and an emptied text returns to the plan', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    app(UpdateHomeContent::class)->handle($editor, ['king' => ['text' => 'نص معدّل للاختبار'], 'values' => ['items' => array_fill(0, 4, ['title' => null, 'text' => null, 'image' => null])]]);
    $this->get('/')->assertSee('نص معدّل للاختبار');

    app(UpdateHomeContent::class)->handle($editor, ['king' => ['text' => ''], 'values' => ['items' => array_fill(0, 4, [])]]);
    $this->get('/')->assertSee(__('home.sections.king.text'));

    expect(fn () => app(UpdateHomeContent::class)->handle(userWithRole(RoleName::SuperAdmin), ['king' => ['text' => 'x'], 'values' => ['items' => array_fill(0, 4, [])]]))
        ->toThrow(AuthorizationException::class);
});

it('rejects a home image outside the settings folder', function () {
    expect(fn () => app(UpdateHomeContent::class)->handle(userWithRole(RoleName::TechnicalAdmin), ['king' => ['image' => '../.env'], 'values' => ['items' => array_fill(0, 4, [])]]))
        ->toThrow(ValidationException::class);

    expect(HomeContent::imagePaths(Setting::group(HomeContent::KEY)))->toBe([]);
});

it('shows announcements within their display days, filters them by type, and sends a store announcement to the store', function () {
    $current = Announcement::factory()->create(['title' => 'إعلان حاضر', 'kind' => AnnouncementKind::Programs, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDay()]);
    Announcement::factory()->create(['title' => 'إعلان منتهٍ', 'ends_on' => today()->subDay()]);
    Announcement::factory()->create(['title' => 'إعلان قادم', 'starts_on' => today()->addDays(2)]);
    Announcement::factory()->unpublished()->create(['title' => 'إعلان مسودة']);
    $store = Announcement::factory()->create(['title' => 'منتج المتجر', 'kind' => AnnouncementKind::Store, 'url' => null]);

    $this->get('/media/announcements')->assertOk()->assertSee('إعلان حاضر')->assertSee('منتج المتجر')
        ->assertDontSee('إعلان منتهٍ')->assertDontSee('إعلان قادم')->assertDontSee('إعلان مسودة');
    $this->get('/media/announcements?kind=programs')->assertSee('إعلان حاضر')->assertDontSee('منتج المتجر');

    expect($store->href())->toBe(route('store.index'))
        ->and($store->opensExternally())->toBeTrue()
        ->and($current->href())->toBe($current->url);
});

it('lets the director, the technical admin and the editor manage announcements, and the super admin only look', function () {
    foreach ([RoleName::ExecutiveDirector, RoleName::TechnicalAdmin, RoleName::ContentEditor] as $role) {
        expect(userWithRole($role)->can('create', Announcement::class))->toBeTrue($role->value);
    }

    $super = userWithRole(RoleName::SuperAdmin);
    expect($super->can('viewAny', Announcement::class))->toBeTrue()
        ->and($super->can('create', Announcement::class))->toBeFalse()
        ->and($super->can('delete', Announcement::factory()->create()))->toBeFalse();
});

it('builds the menu with «مكتبة إعجاز» and its four branches, and the media centre with all its sections', function () {
    $this->seed(MenuSeeder::class);

    $menu = $this->get('/')->getContent();
    $library = strpos($menu, 'مكتبة إعجاز');

    expect($library)->not->toBeFalse();
    foreach (['المكتبة المرئية', 'المكتبة الصوتية', 'الكتب والمؤلفات', 'الأبحاث والدراسات', '/media/announcements'] as $item) {
        expect($menu)->toContain($item);
    }
    // كل أقسام المركز الإعلامي منذ ملاحظات المدير في 1 نوفمبر 2026 (البند ٤)، ومنها مكتبة الصور
    expect($menu)->toContain('href="'.url('/media/albums').'"');
});

it('takes video and audio by YouTube link, keeps an older audio file working, and refuses a new one', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    $audio = app(SaveLibraryItem::class)->handle($editor, new LibraryItem, ['title' => 'صوتية', 'slug' => 'audio-link', 'section' => LibrarySection::Audio,
        'video_url' => 'https://www.youtube.com/watch?v=dQw4w9WgXcQ', 'is_published' => true]);
    expect($audio->youtubeId())->toBe('dQw4w9WgXcQ');

    $this->get('/library/audio/audio-link')->assertOk()->assertSee('youtube-nocookie.com/embed/dQw4w9WgXcQ', false);

    $legacy = LibraryItem::factory()->create(['section' => LibrarySection::Audio, 'file' => storedPrivateFile(LibraryItem::FILE_DIRECTORY, mp3Contents(), 'mp3'), 'video_url' => null, 'is_published' => true]);
    expect($legacy->hasSource())->toBeTrue();
    app(SaveLibraryItem::class)->handle($editor, $legacy, ['title' => 'عنوان معدّل']);
    expect($legacy->fresh()->file)->not->toBeNull();

    expect(fn () => app(SaveLibraryItem::class)->handle($editor, new LibraryItem, ['title' => 'ملف جديد', 'slug' => 'new-audio-file', 'section' => LibrarySection::Audio,
        'file' => storedPrivateFile(LibraryItem::FILE_DIRECTORY, mp3Contents(), 'mp3')]))->toThrow(ValidationException::class);

    // ولا اتصال من متصفح الزائر بجوجل قبل ضغطه: لا صورة من i.ytimg.com في الصفحة ولا في سياسة أمان المحتوى
    $page = $this->get('/library/audio/audio-link');
    expect($page->getContent())->not->toContain('i.ytimg.com')
        ->and($page->headers->get('Content-Security-Policy'))->not->toContain('ytimg');
});

it('builds the beneficiary dashboard cards from the panel: added, hidden, by audience, and section cards follow their section', function () {
    $member = userWithRole(RoleName::Member);
    AccountCard::factory()->create(['title' => 'بطاقة مضافة', 'url' => '/programs', 'icon' => SiteIcon::Link]);
    AccountCard::factory()->create(['title' => 'بطاقة مخفية', 'url' => '/programs', 'is_visible' => false]);
    AccountCard::factory()->create(['title' => 'بطاقة الإدارة فقط', 'url' => '/admin', 'audience' => AccountCardAudience::Panel]);

    $titles = AccountNavigation::cards($member)->pluck('title')->all();
    expect($titles)->toContain('بطاقة مضافة', __('account.nav.profile'))
        ->not->toContain('بطاقة مخفية', 'بطاقة الإدارة فقط', __('assembly.nav'));

    expect(AccountNavigation::cards(userWithRole(RoleName::SuperAdmin))->pluck('title')->all())->toContain('بطاقة الإدارة فقط', __('assembly.nav'));

    $builtIn = AccountCard::query()->whereNotNull('route')->firstOrFail();
    expect(userWithRole(RoleName::TechnicalAdmin)->can('delete', $builtIn))->toBeFalse()
        ->and(userWithRole(RoleName::TechnicalAdmin)->can('update', $builtIn))->toBeTrue()
        ->and($member->can('update', $builtIn))->toBeFalse();

    $this->actingAs($member)->get('/account')->assertOk()->assertSee('بطاقة مضافة')->assertDontSee('بطاقة مخفية');
});

it('hides the deferred board, staff and assembly portals until the panel shows them, the permissions unchanged', function () {
    $board = userWithRole(RoleName::BoardMember);
    $assembly = userWithRole(RoleName::AssemblyMember);

    expect(collect(Portals::for($board))->pluck('key')->all())->not->toContain('board')
        ->and(AccountNavigation::for($assembly)->pluck('route')->all())->not->toContain('assembly.index')
        ->and($assembly->can('accessAssemblyArea'))->toBeTrue();

    Setting::put(AccountSettings::KEY, ['verification' => false, 'show_deferred_portals' => true]);

    expect(collect(Portals::for($board))->pluck('key')->all())->toContain('board')
        ->and(AccountNavigation::for($assembly)->pluck('route')->all())->toContain('assembly.index');
});

it('gives the super admin his dashboard: the general figures and the next Teams meeting', function () {
    $super = userWithRole(RoleName::SuperAdmin);
    Meeting::factory()->create(['title' => 'اجتماع قادم', 'body' => MeetingBody::GeneralAssembly, 'status' => MeetingStatus::Scheduled,
        'starts_at' => now()->addDay(), 'online_join_url' => 'https://teams.microsoft.com/l/meetup-join/test']);
    PortalStats::refresh();

    $this->actingAs($super)->get('/account')->assertOk()
        ->assertSee(__('account.oversight.title'))
        ->assertSee(__('account.oversight.join_teams'))
        ->assertSee('https://teams.microsoft.com/l/meetup-join/test', false)
        ->assertSee(__('account.nav.admin'));

    $this->actingAs(userWithRole(RoleName::Member))->get('/account')->assertDontSee(__('account.oversight.title'))->assertDontSee(__('account.oversight.join_teams'));
});

it('turns the short national address into the map link on the contact page', function () {
    expect(NationalAddress::normalize(' abcd 1234 '))->toBe('ABCD1234')
        ->and(NationalAddress::normalize('ABC12345'))->toBeNull()
        ->and(NationalAddress::mapUrl('ABCD1234'))->toBe('https://www.google.com/maps/search/?api=1&query=ABCD1234');

    Setting::put('contact', ['short_address' => 'ABCD1234']);
    $this->get('/contact')->assertSee('https://www.google.com/maps/search/?api=1&amp;query=ABCD1234', false)->assertSee('ABCD1234');
});

it('grants an existing account the technical admin role from the command without asking for a password', function () {
    $safwan = User::factory()->create(['email' => 'tech@example.test']);

    $this->artisan('ejaz:create-admin')
        ->expectsQuestion('البريد الإلكتروني', 'tech@example.test')
        ->expectsConfirmation('للبريد حساب قائم باسم «'.$safwan->name.'». أيُمنح دور '.RoleName::TechnicalAdmin->getLabel().'؟', 'yes')
        ->assertSuccessful();

    expect($safwan->fresh()->hasRole(RoleName::TechnicalAdmin->value))->toBeTrue()
        ->and(User::query()->count())->toBe(1);
});
