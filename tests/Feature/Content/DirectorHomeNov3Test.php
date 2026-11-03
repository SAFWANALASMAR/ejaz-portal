<?php

use App\Enums\LibrarySection;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\Icon;
use Illuminate\Support\Facades\Storage;

/*
| توجيه المدير التنفيذي (3 نوفمبر 2026 — docs/decisions/2026-11-03-director-home.md): ترتيب الرئيسية من الأعلى، والبطاقات الخمس،
| واتجاه الشريطين، و«البوابة بالأرقام» أربع بطاقات، وترتيب الصف الأول من الرأس. والمواضع والاتجاه الفعلي على الشاشة مقيسة في
| tests/browser/director-home-2026-11-03.spec.mjs
*/

function directorHomeContent(): void
{
    Initiative::factory()->create(['name' => 'مبادرة الاختبار', 'is_published' => true]);
    LibraryItem::factory()->video()->count(6)->create();
    LibraryItem::factory()->count(8)->create(['section' => LibrarySection::Books]);
    NewsArticle::factory()->create();
    Announcement::factory()->create();
    Partner::factory()->create();
    Setting::put('contact', ['social' => ['x' => 'https://x.com/ejaz_sa']]);
    Setting::put(HomeContent::KEY, [
        'portal_stats' => ['enabled' => true],
        'values' => ['items' => [['title' => 'قيمة الاختبار', 'text' => 'وصفها', 'icon' => 'star']]],
    ]);
}

function directorVisits(int ...$visitors): void
{
    foreach ($visitors as $daysAgo => $count) {
        DailyVisit::query()->create(['date' => now(config('app.display_timezone'))->subDays($daysAgo)->toDateString(), 'visitors' => $count, 'views' => $count]);
    }
}

it('orders the home sections as the director asked, from the hero to the footer', function () {
    directorHomeContent();
    directorVisits(12, 8);
    $this->artisan('ejaz:refresh-stats');

    $html = $this->get('/')->assertOk()->getContent();
    $markers = [
        'hero' => 'data-hero-slider',
        'identity' => 'id="home-identity"',
        'services' => 'aria-labelledby="home-shortcuts"',
        'videos' => 'data-media-strip="videos"',
        'books' => 'data-media-strip="books"',
        'x' => 'data-x-card',
        'announcements' => 'id="home-announcements"',
        'news' => 'id="home-news"',
        'stats' => 'data-portal-stats',
        'partners' => 'aria-labelledby="home-partners"',
        'footer' => '<footer',
    ];
    $positions = array_map(fn (string $marker): int|false => strpos($html, $marker), $markers);

    expect(array_filter($positions, fn (int|false $position): bool => $position === false))->toBe([]);
    asort($positions);
    expect(array_keys($positions))->toBe(array_keys($markers));
});

it('shows the five identity cards in order, with their inner carousels', function () {
    directorHomeContent();

    $html = (string) str($this->get('/')->assertOk()->getContent())->after('id="home-identity"')->before('</section>');
    $first = (string) str($html)->before('data-marquee-copy');
    preg_match_all('/data-identity-card="(\w+)"/', $first, $cards);

    expect($cards[1])->toBe(['vision', 'goals', 'values', 'principles', 'initiatives'])
        ->and($first)->toContain('data-values-slider')->toContain('data-initiatives-slider')
        ->and($first)->toContain('قيمة الاختبار')->toContain('مبادرة الاختبار');
});

it('runs the YouTube strip in the normal direction and the library strip under it in reverse, both full width', function () {
    directorHomeContent();

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    preg_match('/<div ([^>]*data-media-strip="videos"[^>]*)>/', $strips, $videos);
    preg_match('/<div ([^>]*data-media-strip="books"[^>]*)>/', $strips, $books);

    // شريط يوتيوب بحركة marquee-x (نحو اليمين)، وشريط المكتبة معكوسها (نحو اليسار — concept.css)، ويوتيوب أولاً ثم المكتبة ثم X
    expect($videos[1] ?? null)->toContain('media-marquee')->not->toContain('media-marquee-reverse')
        ->and($books[1] ?? null)->toContain('media-marquee-reverse')
        ->and(strpos($strips, 'data-media-strip="videos"'))->toBeLessThan(strpos($strips, 'data-media-strip="books"'))
        ->and(strpos($strips, 'data-media-strip="books"'))->toBeLessThan(strpos($strips, 'data-x-card'))
        ->and(substr_count($strips, 'data-marquee data-direction="horizontal" data-loop'))->toBe(2);
});

it('shows the four portal cards as a 2×2 grid beside the 30-day chart, with the library cards at their real zero', function () {
    directorHomeContent();
    directorVisits(30, 20, 10);
    CompetitionSubmission::factory()->create(['competition_id' => Competition::factory()->create()->id, 'registered_at' => now()]);
    $this->artisan('ejaz:refresh-stats');

    $section = (string) str($this->get('/')->assertOk()->getContent())->after('data-portal-stats')->before('</section>');

    expect(substr_count($section, 'data-stat-badge'))->toBe(4)
        ->and($section)->toContain('data-portal-chart')
        ->and($section)->toContain('grid grid-cols-2 gap-3.5')
        ->and($section)->toContain('data-count-to="60"')
        ->and($section)->toContain('data-count-to="1"')
        ->and(substr_count($section, 'data-count-to="0"'))->toBe(2);

    // البطاقات قبل الرسم في الشيفرة، فهي يميناً (RTL)، وبالترتيب
    expect(strpos($section, __('home.portal_stats.media_views')))->toBeLessThan(strpos($section, 'data-portal-chart'))
        ->and(strpos($section, __('home.portal_stats.visits')))->toBeLessThan(strpos($section, __('home.portal_stats.registrants')))
        ->and(strpos($section, __('home.portal_stats.registrants')))->toBeLessThan(strpos($section, __('home.portal_stats.book_reads')))
        ->and(strpos($section, __('home.portal_stats.book_reads')))->toBeLessThan(strpos($section, __('home.portal_stats.media_views')));
});

it('shows the four portal cards even when all four numbers are zero, and none before the first run or with the section switched off', function () {
    directorHomeContent();
    directorVisits(0, 0);
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false)->assertDontSee('data-stat-badge', false);

    ContentCache::flush();
    $this->artisan('ejaz:refresh-stats');
    $section = (string) str($this->get('/')->assertOk()->getContent())->after('data-portal-stats')->before('</section>');
    expect(substr_count($section, 'data-stat-badge'))->toBe(4)
        ->and(substr_count($section, 'data-count-to="0"'))->toBe(4);

    DailyVisit::query()->update(['visitors' => 5]);
    Setting::put(HomeContent::KEY, ['portal_stats' => ['enabled' => false]]);
    $this->artisan('ejaz:refresh-stats');

    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false)->assertDontSee(__('home.portal_stats.visits'));
});

it('keeps the first header row as mark, calligraphy, licence, then search and login, with the logo images unchanged', function () {
    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);

    $row = $x->query("//header[@data-site-header]/div[contains(concat(' ', @class, ' '), ' header-top ')]")->item(0);
    $items = $x->query(".//img[@data-brand-mark] | .//img[@data-logo-calligraphy] | .//*[@data-header-licence] | .//*[@data-search-open] | .//a[contains(@class, 'login-pill')]", $row);
    $names = [];
    foreach ($items as $item) {
        $names[] = match (true) {
            $item->hasAttribute('data-brand-mark') => 'mark',
            $item->hasAttribute('data-logo-calligraphy') => 'calligraphy',
            $item->hasAttribute('data-header-licence') => 'licence',
            $item->hasAttribute('data-search-open') => 'search',
            default => 'login',
        };
    }

    expect($names)->toBe(['mark', 'calligraphy', 'licence', 'search', 'login'])
        ->and($x->query('.//img[@data-brand-mark]', $row)->item(0)->getAttribute('src'))->toBe(HomeContent::brand('mark_white'))
        ->and($x->query('.//img[@data-logo-calligraphy]', $row)->item(0)->getAttribute('src'))->toBe(HomeContent::brand('calligraphy_white'));

    // الكتلة يميناً والأزرار يساراً بهامش ثابت، لا بتوزيع الفراغات المتساوية (#154)
    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect(preg_match('/\.header-top\s*\{[^}]*space-between/', $css))->toBe(0)
        ->and(preg_match('/\.header-top \.header-actions\s*\{\s*margin-inline: auto 1\.75rem;/', $css))->toBe(1)
        ->and(preg_match('/\.header-top \.header-mark\s*\{\s*margin-inline-start: 1\.75rem;/', $css))->toBe(1);
});

/*
| إضافة التوجيه (3 نوفمبر 2026، 20:39): صف القائمة لا يُخفى على الحاسوب واللوحي الأفقي، وشريط المكتبة «الكتب والأبحاث والدراسات»
| بأيقونة الكتاب للبحث بلا غلاف، وبحثان تجريبيان لرابط العرض والبيئة المحلية وحدهما.
*/
it('never hides the menu row behind the menu button on desktop and the landscape tablet', function () {
    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);

    expect($x->query("//nav[@data-main-nav][contains(concat(' ', @class, ' '), ' lg:block ')]")->length)->toBe(1)
        ->and($x->query("//button[@data-toggle='mobile-menu'][contains(concat(' ', @class, ' '), ' lg:hidden ')]")->length)->toBe(1);

    // لا قاعدة تخفي الصف أو تُظهر زر القائمة حين لا يتسع، بل خط وحشو أصغر
    foreach (['resources/css/app.css', 'resources/css/concept.css'] as $file) {
        expect(preg_match('/\[data-nav-overflow\][^{]*\{[^}]*display:\s*(none|block|inline-block)/', (string) file_get_contents(base_path($file))))->toBe(0, $file);
    }
    expect(preg_match('/\[data-nav-overflow\] \.nav-capsule\s*\{[^}]*font-size/', (string) file_get_contents(resource_path('css/concept.css'))))->toBe(1);
});

it('titles the library strip «الكتب والأبحاث والدراسات», shows published research with the same book icon as books', function () {
    LibraryItem::factory()->create(['title' => 'كتاب الشريط', 'section' => LibrarySection::Books]);
    LibraryItem::factory()->create(['title' => 'بحث الشريط', 'section' => LibrarySection::Research]);
    LibraryItem::factory()->unpublished()->create(['title' => 'بحث غير منشور', 'section' => LibrarySection::Research]);

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    $books = (string) str($strips)->after('data-media-strip="books"');
    preg_match_all('/data-thumb-placeholder aria-hidden="true">\s*(<svg.*?<\/svg>)/s', $books, $icons);

    expect($strips)->toContain(__('home.sections_final.headings.books'))
        ->and(__('home.sections_final.headings.books'))->toBe('من مكتبة إعجاز: الكتب والأبحاث والدراسات')
        ->and($books)->toContain('بحث الشريط')->toContain('كتاب الشريط')->not->toContain('بحث غير منشور')
        ->and(count($icons[1]))->toBe(2)
        ->and(array_unique($icons[1]))->toHaveCount(1)
        ->and($icons[1][0])->toBe(Icon::svg(LibrarySection::Books->icon(), 'size-10')->toHtml());
});

it('adds two demo research items marked «تجريبي» when none is published, and nothing when one is', function () {
    Storage::fake(LibraryItem::disk());
    $this->artisan('ejaz:demo-research')->assertSuccessful();

    $items = LibraryItem::query()->where('section', LibrarySection::Research)->orderBy('slug')->get();
    expect($items)->toHaveCount(2)
        ->and($items->pluck('title')->all())->each->toContain('تجريبي')
        ->and($items->every(fn (LibraryItem $item): bool => $item->is_published && $item->thumbnail === null))->toBeTrue();
    foreach ($items as $item) {
        Storage::disk(LibraryItem::disk())->assertExists($item->file);
    }

    $this->get('/')->assertOk()->assertSee(__('home.demo_research.title', ['number' => 1]));

    $this->artisan('ejaz:demo-research')->assertSuccessful();
    expect(LibraryItem::query()->where('section', LibrarySection::Research)->count())->toBe(2);
});

it('refuses to add the demo research outside the local environment', function () {
    app()->detectEnvironment(fn (): string => 'production');

    $this->artisan('ejaz:demo-research')->assertFailed();

    expect(LibraryItem::query()->where('section', LibrarySection::Research)->count())->toBe(0);
});
