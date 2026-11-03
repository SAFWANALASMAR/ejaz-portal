<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Filament\Pages\HomeContentPage;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use App\Models\LibraryItem;
use App\Models\Setting;
use App\Models\User;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\HomePortalStats;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

/*
| «البوابة بالأرقام» العامة (البند H9 — docs/decisions/2026-11-01-site-stats-v2.md): مجاميع من الجداول القائمة، مخزنة في الخلفية،
| بمفتاح إظهار في «محتوى الرئيسية»، وبلا بيانات شخصية.
*/

function visitsOn(int $daysAgo, int $visitors, int $views = 0): void
{
    DailyVisit::query()->create([
        'date' => now(config('app.display_timezone'))->subDays($daysAgo)->toDateString(),
        'visitors' => $visitors,
        'views' => max($views, $visitors),
    ]);
}

/**
 * صف تقدّم في المكتبة كما يحفظه «قراءاتي»: صفحات مقروءة، أو ثوانٍ مستهلكة لمقطع.
 */
function libraryProgressOf(User $user, LibraryItem $item, int $pages = 0, int $seconds = 0): void
{
    DB::table('library_progress')->insert([
        'user_id' => $user->id, 'library_item_id' => $item->id, 'pages_count' => $pages, 'seconds_consumed' => $seconds,
        'last_reported_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function portalContent(User $actor, array $content): void
{
    app(UpdateHomeContent::class)->handle($actor, ['values' => ['items' => array_fill(0, HomeContent::VALUES, [])], ...$content]);
}

// القسم مطفأ افتراضياً (مراجعة المشرف على #115)، فتشغّله الاختبارات إلا اختبار الافتراضي
beforeEach(function () {
    Setting::put(HomeContent::KEY, ['portal_stats' => ['enabled' => true]]);
});

it('is off by default until switched on from «محتوى الرئيسية»', function () {
    Setting::query()->where('key', HomeContent::KEY)->delete();
    visitsOn(0, 30);
    $this->artisan('ejaz:refresh-stats');

    expect(HomeContent::showsPortalStats())->toBeFalse();
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    portalContent(userWithRole(RoleName::ContentEditor), ['portal_stats' => ['enabled' => true]]);

    expect(HomeContent::showsPortalStats())->toBeTrue();
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false);
});

it('shows the visits, the competition registrants and the 30-day chart from the stored aggregates', function () {
    visitsOn(40, 5000);
    visitsOn(3, 700);
    visitsOn(0, 534);
    $competition = Competition::factory()->create();
    CompetitionSubmission::factory()->create(['competition_id' => $competition->id, 'registered_at' => now()]);
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => $competition->id]);

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    $html = $this->get('/')->assertOk()
        ->assertSee('data-portal-stats', false)
        ->assertSee('data-portal-chart', false)
        ->assertSee(__('home.sections_final.headings.stats'))
        ->assertSee(__('home.sections_final.headings.stats_intro'))
        ->assertSee(__('home.portal_stats.visits'))
        ->assertSee(__('home.portal_stats.registrants'))
        ->assertSee(__('home.portal_stats.chart_title'))
        ->getContent();

    // الرقم كاملاً بفواصل الآلاف للعدّ التصاعدي وبلا سكربت، والرسم بمجموع آخر 30 يوماً وحده
    expect($html)->toContain('data-count-to="6234"')->toContain('6,234')
        ->and($html)->toContain('data-count-to="2"')
        ->and($html)->toContain(__('home.portal_stats.chart_label', ['days' => HomePortalStats::DAYS, 'total' => '1,234', 'max' => '700']));

    // «البوابة بالأرقام» آخراً قبل «شركاؤنا»
    expect(strpos($html, 'data-portal-stats'))->toBeGreaterThan(strpos($html, 'id="home-identity"'));
});

it('hides the section while there is nothing to show, and before the first background run', function () {
    // قبل أول حساب لا رقم ولا استعلام
    visitsOn(0, 10);
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    DailyVisit::query()->delete();
    ContentCache::flush();
    $this->artisan('ejaz:refresh-stats');
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    // الصفر لا يظهر بطاقة، ولا رسم لأيام بلا زيارة
    visitsOn(1, 0);
    $this->artisan('ejaz:refresh-stats');
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);
});

it('counts registrants in published competitions only, once each, without cancelled ones or untouched drafts', function () {
    $published = Competition::factory()->create();
    $student = CompetitionSubmission::factory()->create(['competition_id' => $published->id, 'registered_at' => now()]);
    // المسجل في مسابقتين منشورتين يُعدّ مرة
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => Competition::factory()->create()->id, 'user_id' => $student->user_id]);
    CompetitionSubmission::factory()->create(['competition_id' => $published->id]);
    CompetitionSubmission::factory()->create(['competition_id' => $published->id, 'registered_at' => now()])->delete();
    CompetitionSubmission::factory()->submitted()->create(['competition_id' => Competition::factory()->unpublished()->create()->id]);

    expect(HomePortalStats::compute()['registrants'])->toBe(1);
});

// بعدد المرات من سجل «قراءاتي» لا بالصفحات ولا بالدقائق (قرار صفوان، 3 نوفمبر — docs/decisions/2026-11-03-portal-stats-counts.md):
// كل قارئ لكتاب أو بحث قراءة واحدة مهما قرأ من صفحاته، وكل مستمع أو مشاهد لمقطع مرئي أو صوتي مشاهدة واحدة
it('shows the library cards after the visits and registrants, by the number of reads and views', function () {
    visitsOn(0, 50);
    CompetitionSubmission::factory()->create(['competition_id' => Competition::factory()->create()->id, 'registered_at' => now()]);
    [$first, $second, $third] = User::factory()->count(3)->create();
    $book = LibraryItem::factory()->create();
    libraryProgressOf($first, $book, pages: 1200);
    libraryProgressOf($second, $book, pages: 34);
    libraryProgressOf($third, LibraryItem::factory()->create(['section' => LibrarySection::Research]), pages: 2);
    // المرئي والصوتي معاً: مشاهدتان مهما طالتا
    $video = LibraryItem::factory()->video()->create();
    libraryProgressOf($first, $video, seconds: 630);
    libraryProgressOf($second, LibraryItem::factory()->audio()->create(), seconds: 180);
    // فُتحت المادة ولم يُقرأ منها شيء: لا قراءة ولا مشاهدة
    libraryProgressOf($second, $video);
    libraryProgressOf($first, LibraryItem::factory()->create());

    $this->artisan('ejaz:refresh-stats')->assertSuccessful();

    expect(HomePortalStats::cached())->toMatchArray(['book_reads' => 3, 'media_views' => 2]);

    $html = $this->get('/')->assertOk()
        ->assertSeeInOrder([__('home.portal_stats.visits'), __('home.portal_stats.registrants'), __('home.portal_stats.book_reads'), __('home.portal_stats.media_views')])
        ->assertDontSee('صفحة مقروءة في المكتبة')->assertDontSee('دقيقة مشاهدة واستماع')
        ->getContent();

    // رقمان مختلفان، فيُعرف كل منهما في بطاقته
    expect($html)->toContain('data-count-to="3"')->toContain('data-count-to="2"')->not->toContain('data-count-to="1234"');
});

// توجيه المدير التنفيذي (3 نوفمبر 2026 — docs/decisions/2026-11-03-director-home.md): البطاقات الأربع معاً 2×2 كما كانت، والصفر
// رقمها الحقيقي. كانت كل بطاقة صفرية تُخفى، فبقيت بطاقتان في العرض الذي لا قراءة فيه في «قراءاتي» بعد
it('shows the four cards together, a library card at its real zero, and keeps a store from before the library cards', function () {
    visitsOn(0, 9);
    // تقدّم بلا صفحة ولا ثانية: قراءة ومشاهدة بصفر، والبطاقتان ظاهرتان برقمهما
    libraryProgressOf(User::factory()->create(), LibraryItem::factory()->create());
    $this->artisan('ejaz:refresh-stats');

    $html = $this->get('/')->assertOk()
        ->assertSeeInOrder([__('home.portal_stats.visits'), __('home.portal_stats.registrants'), __('home.portal_stats.book_reads'), __('home.portal_stats.media_views')])
        ->getContent();
    $section = (string) str($html)->after('data-portal-stats')->before('</section>');
    expect(substr_count($section, 'data-stat-badge'))->toBe(4)
        ->and(substr_count($section, 'data-count-to="0"'))->toBe(3)
        ->and($section)->toContain('data-count-to="9"');

    // مشاهدة بلا قراءة: المشاهدات برقمها، والقراءات بصفر
    libraryProgressOf(User::factory()->create(), LibraryItem::factory()->audio()->create(), seconds: 120);
    $this->artisan('ejaz:refresh-stats');
    $section = (string) str($this->get('/')->assertOk()->getContent())->after('data-portal-stats')->before('</section>');
    expect(substr_count($section, 'data-stat-badge'))->toBe(4)
        ->and(substr_count($section, 'data-count-to="0"'))->toBe(2)
        ->and(substr_count($section, 'data-count-to="1"'))->toBe(1);

    // المخزّن بشكله السابق (بالصفحات والدقائق، أو بلا مفتاحي المكتبة) يبقى معروضاً بصفر لهما حتى الحساب التالي، لا برقم الصفحات القديم
    Cache::forever(HomePortalStats::KEY, ['visits' => 9, 'registrants' => 0, 'pages_read' => 1234, 'media_minutes' => 13, 'days' => [9]]);
    ContentCache::flush();
    expect(HomePortalStats::cached())->toBe(['visits' => 9, 'registrants' => 0, 'book_reads' => 0, 'media_views' => 0, 'days' => [9]]);
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)->assertSee(__('home.portal_stats.visits'))
        ->assertSee(__('home.portal_stats.media_views'))->assertDontSee('data-count-to="1234"', false)->assertDontSee('data-count-to="13"', false);
});

it('keeps aggregates alone, with no personal data in the store or the page', function () {
    visitsOn(0, 12);
    $user = User::factory()->create(['name' => 'اسم شخصي للاختبار', 'email' => 'private-person@example.test']);
    CompetitionSubmission::factory()->create([
        'competition_id' => Competition::factory()->create()->id,
        'user_id' => $user->id,
        'registered_at' => now(),
        'full_name' => 'طالب سري للاختبار',
    ]);
    libraryProgressOf($user, LibraryItem::factory()->create(), pages: 7);

    $this->artisan('ejaz:refresh-stats');

    expect(array_keys((array) HomePortalStats::cached()))->toBe(['visits', 'registrants', 'book_reads', 'media_views', 'days'])
        ->and(HomePortalStats::cached()['days'])->each->toBeIn([null, 0, 12]);

    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)->assertSee(__('home.portal_stats.book_reads'))
        ->assertDontSee('اسم شخصي للاختبار')->assertDontSee('private-person@example.test')->assertDontSee('طالب سري للاختبار');
});

it('reads the stored numbers alone on a request, with no aggregate queries', function () {
    visitsOn(0, 20);
    CompetitionSubmission::factory()->create(['competition_id' => Competition::factory()->create()->id, 'registered_at' => now()]);
    libraryProgressOf(User::factory()->create(), LibraryItem::factory()->video()->create(), seconds: 600);
    $this->artisan('ejaz:refresh-stats');
    ContentCache::flush();

    DB::enableQueryLog();
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)->assertSee(__('home.portal_stats.media_views'));
    $queries = collect(DB::getQueryLog())->pluck('query');

    expect($queries->filter(fn (string $sql): bool => str_contains($sql, 'daily_visits') || str_contains($sql, 'competition_submissions') || str_contains($sql, 'library_progress')))->toBeEmpty();
});

it('refreshes the home cache when the numbers change while the section shows, and not when switched off', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    visitsOn(0, 5);
    $this->artisan('ejaz:refresh-stats');

    ContentCache::remember('home', fn (): string => 'cached');
    ContentCache::remember('governance.index', fn (): string => 'cached');

    // بلا تغيير لا مسح
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('cached');

    // رقم جديد: تُمسح الرئيسية وحدها
    DailyVisit::query()->update(['visitors' => 9]);
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('fresh')
        ->and(ContentCache::remember('governance.index', fn (): string => 'fresh'))->toBe('cached');

    // والقسم مطفأ: لا مسح ولو تغيّر الرقم
    portalContent($editor, ['portal_stats' => ['enabled' => false]]);
    ContentCache::remember('home', fn (): string => 'cached-off');
    DailyVisit::query()->update(['visitors' => 15]);
    $this->artisan('ejaz:refresh-stats');
    expect(ContentCache::remember('home', fn (): string => 'fresh'))->toBe('cached-off');
});

it('hides the automatic numbers with the switch in «محتوى الرئيسية», and keeps the approved ones from the settings', function () {
    $editor = userWithRole(RoleName::ContentEditor);
    visitsOn(0, 30);
    $this->artisan('ejaz:refresh-stats');

    expect(HomeContent::showsPortalStats())->toBeTrue();

    portalContent($editor, ['portal_stats' => ['enabled' => false]]);

    expect(HomeContent::showsPortalStats())->toBeFalse()
        ->and(Setting::group(HomeContent::KEY)['portal_stats']['enabled'])->toBeFalse();
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false)->assertDontSee(__('home.portal_stats.visits'));

    // والأرقام المعتمدة يدوياً بمفتاحها في «إعدادات الموقع»
    Setting::put('home', ['show_stats' => true, 'stats' => [approvedStat()]]);
    $this->get('/')->assertOk()->assertSee('data-portal-stats', false)->assertSee('مستفيد')
        ->assertDontSee(__('home.portal_stats.visits'))->assertDontSee('data-portal-chart', false);

    portalContent($editor, ['portal_stats' => ['enabled' => true], 'headings' => ['stats' => 'أرقامنا', 'stats_intro' => 'وصف من اللوحة']]);
    $this->get('/')->assertOk()->assertSee('أرقامنا')->assertSee('وصف من اللوحة')->assertSee(__('home.portal_stats.visits'))->assertSee('مستفيد');
});

it('shows the switch and the real default texts in «محتوى الرئيسية», and saves them', function () {
    $this->actingAs(userWithRole(RoleName::ContentEditor));

    // الأصل في حقله لا اسم مفتاحه (home.sections_final)
    Livewire::test(HomeContentPage::class)
        ->assertFormSet([
            'portal_stats.enabled' => true,
            'headings.stats' => __('home.sections_final.headings.stats'),
            'headings.stats_intro' => __('home.sections_final.headings.stats_intro'),
        ])
        ->assertSee(__('settings.home_content_portal_stats.enabled'))
        ->fillForm(['portal_stats.enabled' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(HomeContent::showsPortalStats())->toBeFalse()
        // حفظ الأصل كما هو لا يخزّنه نصاً
        ->and(Setting::group(HomeContent::KEY)['headings']['stats'] ?? null)->toBeNull();
});

it('refuses the switch from a user without the settings permission, or with a wrong value', function () {
    $user = User::factory()->create();

    expect(fn () => portalContent($user, ['portal_stats' => ['enabled' => false]]))->toThrow(AuthorizationException::class);

    expect(fn () => portalContent(userWithRole(RoleName::ContentEditor), ['portal_stats' => ['enabled' => 'نعم']]))->toThrow(ValidationException::class);
    expect(HomeContent::showsPortalStats())->toBeTrue();
});

it('draws the chart from the oldest day on the right to today on the left, without the days before counting began', function () {
    $days = array_fill(0, HomePortalStats::DAYS, null);
    $days[28] = 4;
    $days[29] = 8;

    $chart = HomePortalStats::chart($days);

    expect($chart['line'])->toBe('34.5,117 0,14')
        ->and($chart['area'])->toBe('34.5,220 34.5,117 0,14 0,220')
        ->and($chart['total'])->toBe(12)
        ->and($chart['max'])->toBe(8)
        ->and(HomePortalStats::chart(array_fill(0, HomePortalStats::DAYS, 0)))->toBeNull()
        ->and(HomePortalStats::chart(array_fill(0, HomePortalStats::DAYS, null)))->toBeNull();

    // يوم معدود واحد (أول يوم بعد النشر): نقطة لا تُرسم خطاً، فلا بطاقة رسم فارغة
    $first = array_fill(0, HomePortalStats::DAYS, null);
    $first[29] = 40;
    expect(HomePortalStats::chart($first))->toBeNull();
});

it('keeps the switch as it is when the content is saved without it', function () {
    $editor = userWithRole(RoleName::ContentEditor);

    portalContent($editor, ['portal_stats' => ['enabled' => false]]);
    portalContent($editor, ['headings' => ['stats' => 'عنوان آخر']]);

    expect(HomeContent::showsPortalStats())->toBeFalse()
        ->and(HomeContent::text('headings', 'stats'))->toBe('عنوان آخر');
});
