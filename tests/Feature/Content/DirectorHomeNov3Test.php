<?php

use App\Enums\LibrarySection;
use App\Models\Announcement;
use App\Models\AssistantSettings;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MenuItem;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\HomeContent;
use App\Support\Icon;
use App\Support\LibraryHeroImages;
use Database\Seeders\AboutMenuAnchorsSeeder;
use Database\Seeders\MenuSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Once;

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

// البند ١٧ من الإضافة (يحل محل البند ٤): يوتيوب من اليمين إلى اليسار، والمكتبة من اليسار إلى اليمين كـ«خدماتنا»
// البندان ١٧ و٢٦ من الإضافة: كل شريط عكس الذي قبله — يوتيوب من اليمين إلى اليسار، والكتب من اليسار إلى اليمين، والأبحاث من اليمين إلى اليسار
it('runs the YouTube, books and research strips in alternating directions, each full width', function () {
    directorHomeContent();
    LibraryItem::factory()->count(2)->create(['section' => LibrarySection::Research]);

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    $wrap = fn (string $kind): string => preg_match('/<div ([^>]*data-media-strip="'.$kind.'"[^>]*)>/', $strips, $match) === 1 ? $match[1] : '';

    expect($wrap('videos'))->toContain('media-marquee-flip')
        ->and($wrap('books'))->toContain('media-marquee-books')->not->toContain('media-marquee-flip')
        ->and($wrap('research'))->toContain('media-marquee-books')->toContain('media-marquee-flip')
        ->and(strpos($strips, 'data-media-strip="videos"'))->toBeLessThan(strpos($strips, 'data-media-strip="books"'))
        ->and(strpos($strips, 'data-media-strip="books"'))->toBeLessThan(strpos($strips, 'data-media-strip="research"'))
        ->and(strpos($strips, 'data-media-strip="research"'))->toBeLessThan(strpos($strips, 'data-x-card'))
        ->and(substr_count($strips, 'data-marquee data-direction="horizontal" data-loop'))->toBe(3);
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
    // وكبسولة «المتجر الإلكتروني» تحت الدخول (data-header-store) في StorePageTest
    $items = $x->query(".//img[@data-brand-mark] | .//img[@data-logo-calligraphy] | .//*[@data-header-licence] | .//*[@data-search-open] | .//a[contains(@class, 'login-pill') and not(@data-header-store)]", $row);
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

    // البند ١٤ (أُلغي التوسيط في البند ١٢): الكتلة يميناً والأزرار يساراً بهامش ثابت، لا بتوزيع الفراغات المتساوية (#154)
    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect(preg_match('/\.header-top\s*\{[^}]*space-between/', $css))->toBe(0)
        ->and(preg_match('/\.header-top \.header-end\s*\{\s*display: contents/', $css))->toBe(1)
        ->and(preg_match('/\.header-top \.header-actions\s*\{\s*margin-inline: auto 1\.75rem;/', $css))->toBe(1)
        ->and(preg_match('/\.header-top \.header-mark\s*\{\s*margin-inline-start: 1\.75rem;/', $css))->toBe(1);
});

// البند ١٣ من إضافة التوجيه: «المساعد الذكي» في الصف الأول بجانب البحث والدخول، يفتح المساعد القائم.
// وهو مطفأ يبقى ظاهراً معطّلاً «قيد التفعيل» لا يفتح شيئاً (قرار صفوان، 4 نوفمبر 2026)
it('shows the assistant button beside search and login when the assistant is on, and a disabled one when off', function () {
    assertAssistantSoon($this->get('/')->assertOk()->getContent());

    enableAssistant(['generation_enabled' => false, 'provider' => null]);
    ContentCache::flush();

    $dom = new DOMDocument;
    @$dom->loadHTML($this->get('/')->assertOk()->getContent());
    $x = new DOMXPath($dom);
    $button = $x->query("//header[@data-site-header]//div[contains(@class, 'header-actions')]/button[@data-header-assistant]")->item(0);

    expect($button)->not->toBeNull()
        ->and($button->hasAttribute('data-assistant-toggle'))->toBeTrue()
        ->and($button->getAttribute('aria-controls'))->toBe('assistant-panel')
        ->and($button->getAttribute('aria-label'))->toBe(__('assistant.header.open'))
        ->and(__('assistant.header.open'))->toBe('المساعد الذكي')
        // قبل البحث في الشيفرة، فهو يمينه، والدخول في أقصى اليسار
        ->and($x->query('following-sibling::button[@data-search-open]', $button)->length)->toBe(1)
        // والمساعد نفسه في الصفحة، فيفتحه الزر
        ->and($x->query("//section[@id='assistant-panel']")->length)->toBe(1)
        // والعائم للجوال واللوحي العمودي وحدهما، فلا يغطي البطاقات على الحاسوب (تقييم لقطات التوجيه، البند ١٣)
        ->and($x->query("//button[@data-assistant-fab][contains(concat(' ', @class, ' '), ' lg:hidden ')]")->length)->toBe(1);

    AssistantSettings::query()->update(['is_enabled' => false]);
    Cache::forget(AssistantSettings::CACHE_KEY);
    ContentCache::flush();

    $html = $this->get('/')->assertOk()->assertDontSee('id="assistant-panel"', false)->getContent();
    assertAssistantSoon($html);
});

function assertAssistantSoon(string $html): void
{
    $dom = new DOMDocument;
    @$dom->loadHTML($html);
    $x = new DOMXPath($dom);

    foreach (['data-header-assistant', 'data-menu-assistant'] as $marker) {
        $button = $x->query("//button[@{$marker}]")->item(0);

        expect($button)->not->toBeNull($marker)
            ->and($button->getAttribute('aria-disabled'))->toBe('true')
            ->and($button->hasAttribute('data-assistant-toggle'))->toBeFalse()
            ->and($button->hasAttribute('aria-controls'))->toBeFalse();
    }

    expect($html)->toContain(e(__('assistant.header.soon')));
}

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

// البند ٢٦ من الإضافة (ويعدّل البند ٦): شريط الكتب للكتب وحدها، وتحته شريط «الأبحاث والدراسات» ببطاقة الكتاب نفسها، ويختفي بلا أبحاث
it('splits the books and research strips, the research card a copy of the book card with the same icon', function () {
    LibraryItem::factory()->create(['title' => 'كتاب الشريط', 'section' => LibrarySection::Books]);
    LibraryItem::factory()->create(['title' => 'بحث الشريط', 'section' => LibrarySection::Research]);
    LibraryItem::factory()->unpublished()->create(['title' => 'بحث غير منشور', 'section' => LibrarySection::Research]);

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    // المجموعة الظاهرة وحدها (الشريط القليل يتكرر في نسخ مخفية)
    $books = (string) str($strips)->after('data-media-strip="books"')->before('data-marquee-copy');
    $research = (string) str($strips)->after('data-media-strip="research"')->before('data-marquee-copy');
    $icon = fn (string $html): string => preg_match('/data-thumb-placeholder aria-hidden="true">\s*(<svg.*?<\/svg>)/s', $html, $match) === 1 ? $match[1] : '';

    expect($strips)->toContain(__('home.sections_final.headings.books'))->toContain(__('home.sections_final.headings.research'))
        ->and(__('home.sections_final.headings.research'))->toBe('من مكتبة إعجاز: الأبحاث والدراسات')
        ->and($books)->toContain('كتاب الشريط')->not->toContain('بحث الشريط')
        ->and($research)->toContain('بحث الشريط')->not->toContain('بحث غير منشور')->toContain('media-tile-book')
        ->and($icon($research))->toBe($icon($books))
        ->and($icon($books))->toBe(Icon::svg(LibrarySection::Books->icon(), 'size-10')->toHtml());

    LibraryItem::query()->where('section', LibrarySection::Research)->delete();
    ContentCache::flush();
    $this->get('/')->assertOk()->assertDontSee('data-media-strip="research"', false);
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

/*
| إضافة التوجيه، البنود ١٥ إلى ٢٠ (3 نوفمبر 2026، 22:34 فما بعد).
*/
it('shows partner logos as images and a temporary named box for a partner without a logo (item 18)', function () {
    Partner::factory()->create(['name' => 'شريك بشعار', 'logo' => 'partners/logo.png']);
    Partner::factory()->create(['name' => 'شريك بلا شعار', 'logo' => null]);

    $section = (string) str($this->get('/')->assertOk()->getContent())->after('aria-labelledby="home-partners"')->before('</section>');
    $visible = (string) str($section)->before('data-marquee-copy');

    expect($visible)->toContain('alt="شريك بشعار"')->toContain('data-partner-logo')
        ->and($visible)->toMatch('/data-partner-placeholder>\s*شريك بلا شعار\s*</')
        ->and(substr_count($visible, 'data-partner>') + substr_count($visible, 'data-partner '))->toBeGreaterThanOrEqual(2)
        ->and($section)->toContain('data-marquee data-direction="horizontal" data-loop');
});

it('numbers the YouTube cards in solid white (item 19)', function () {
    LibraryItem::factory()->video()->count(6)->create();

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    preg_match('/<span data-video-number[^>]*class="([^"]*)"/', $strips, $number);

    expect($number[1] ?? '')->toContain('text-white ')->not->toContain('text-white/');
});

// البندان ٢٢ و٢٣ من الإضافة: البطل «فاتح بصورة واحدة» بأربع شرائح بالترتيب، وشريحة خادم الحرمين وحدها بحلقة ذهبية وخضراء، وما لا
// صورة معتمدة له مربع بعلامة «【ناقص】»، والبراهين خارج البطل
it('shows the four light hero slides in order — the Mushaf, the Haram, the King, the chairman — without the Brahin slide', function () {
    Competition::factory()->create(['student_mode' => true]);

    $html = $this->get('/')->assertOk()->getContent();
    $hero = (string) str($html)->after('data-hero-slider')->before('</section>');
    preg_match_all('/data-hero-slide="(\w+)"/', $hero, $slides);

    expect($slides[1])->toBe(['about', 'haram', 'king', 'chairman'])
        ->and($hero)->toContain('hero-light')->toContain('data-interval="6000"')
        ->and(substr_count($hero, 'class="hero-light-frame" data-hero-frame'))->toBe(4)
        ->and($hero)->toContain(__('ui.hero.haram_title'))
        // الحرم بلا صورة مرفوعة: مربع محايد بعلامته
        ->and($hero)->toMatch('/data-hero-slide="haram".*?data-missing.*?data-hero-image-missing/s');

    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect($css)->toMatch("/\.hero-light \[data-hero-slide='king'\] \.hero-light-frame \{\s*box-shadow:\s*0 0 0 4px #c9a961,\s*0 0 0 10px rgb\(14 107 71/");
});

// تقييم لقطات التوجيه (4 نوفمبر 2026، البنود ٦ و٧ و٩): «تعرّف علينا» داكناً و«خدماتنا» بإطار في الأولى بشارتين من docs/overview.md،
// و«عن الجمعية» في الثانية، واقتباس خادم الحرمين كاملاً بلا قص، وشريحة رئيس المجلس بعنوان أصغر
it('gives the first two hero slides their buttons and badges, and shows the King quote in full', function () {
    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true]);

    $hero = (string) str($this->get('/')->assertOk()->getContent())->after('data-hero-slider')->before('</section>');
    $slide = fn (string $key): string => (string) str($hero)->after('data-hero-slide="'.$key.'"')->before('</figure>');

    expect($slide('about'))
        ->toMatch('/<a href="'.preg_quote(route('about'), '/').'"[^>]*class="login-pill" data-hero-button="primary">\s*'.preg_quote(__('home.hero.about_us'), '/').'/')
        ->toMatch('/<a href="'.preg_quote(route('services.index'), '/').'"[^>]*class="login-pill hero-light-secondary" data-hero-button="secondary">\s*'.preg_quote(__('home.hero.our_services'), '/').'/')
        ->toContain(__('home.hero.badge_ministry'))->toContain(e(__('home.hero.badge_seat')))
        ->and($slide('haram'))->toMatch('/<a href="'.preg_quote(route('about'), '/').'"[^>]*data-hero-button="primary">\s*'.preg_quote(__('home.hero.about_society'), '/').'/')
        ->and($slide('king'))->toContain(e(HomeContent::resolved()['king']['text']))->not->toContain('line-clamp')->not->toContain('data-hero-button')
        ->and($slide('chairman'))->toContain('hero-light-title')->toContain(__('ui.hero.chairman_read'));

    $css = (string) file_get_contents(resource_path('css/concept.css'));
    // المؤشر أشرطة رفيعة: 34×6 بشفافية، والنشط 52 بالأزرق الداكن، بلا كتلة رمادية (البند ١٢)
    expect($css)->toMatch('/\.hero-light \.concept-dot::before \{\s*width: 34px;\s*height: 6px;[^}]*background: rgb\(31 69 101 \/ 0\.25\);/')
        ->toMatch('/\.hero-light \.concept-dot\[aria-current\]::before \{\s*width: 52px;\s*background: var\(--hero-light-text\);/')
        ->toMatch('/\.hero-light \.concept-dot,\s*\.hero-light \.concept-dot\[aria-current\] \{\s*background: transparent;/');
});

// لا رابط إلى «عن الجمعية» قبل نشرها، و«خدماتنا» باقٍ
it('leaves the about buttons out of the hero before the about page is published', function () {
    $hero = (string) str($this->get('/')->assertOk()->getContent())->after('data-hero-slider')->before('</section>');

    expect($hero)->not->toContain('href="'.route('about').'"')
        ->toContain('href="'.route('services.index').'"');
});

// البند ٢٧ من الإضافة (ويحل محل ذوبان الصورة في البند ١٥): رأس كل صفحة داخلية «فاتح بصورة واحدة» من المكوّن نفسه، بدرجة لكل قسم
it('draws every inner page hero light with its own shade, dark text and the section image in a tilted white frame', function () {
    foreach (['/contact', '/governance', '/library', '/media', '/services', '/programs', '/competitions', '/initiatives'] as $path) {
        $html = (string) $this->get($path)->assertOk()->getContent();

        expect($html)->toMatch('/data-page-hero class="page-hero-light" data-shade="[1-4]"/', $path)
            ->and($html)->toContain('data-page-hero-visual')->toContain('data-page-hero-image')
            ->and($html)->not->toContain('concept-hero-scrim');
    }

    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect($css)->toMatch('/\.page-hero-frame \{[^}]*border: 8px solid var\(--color-white\);[^}]*transform: rotate\(-3deg\);/s')
        ->toMatch('/\.page-hero-back \{[^}]*transform: translate\(-1\.6rem, 0\.75rem\) rotate\(5deg\);/s');
});

// قرار صفوان (4 نوفمبر 2026، البند ٣٢ — تقييم اللقطات، البند ٣): مخطوطة الرأس الفاتح بأزرق الهوية الداكن بلا ظل، قناعاً من ملف المخطوطة نفسه
it('draws the inner page hero calligraphy in brand blue with no shadow, masked from the calligraphy file itself (item 32)', function () {
    $html = (string) $this->get('/contact')->assertOk()->getContent();
    $hero = (string) str($html)->after('data-page-hero ')->before('</section>');

    expect($hero)->toContain('role="img" aria-label="'.e(__('site.name')).'" data-page-calligraphy')
        ->toContain("--calligraphy-mask: url('".HomeContent::brand('calligraphy')."')")
        ->not->toContain('<img src="'.HomeContent::brand('calligraphy').'"');

    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect($css)->toMatch('/\.page-hero-light \.page-hero-calligraphy-mask \{[^}]*filter: none;[^}]*background-color: var\(--hero-light-text\);[^}]*mask: var\(--calligraphy-mask\)/s')
        ->toMatch('/--hero-light-text: #1f4565;/i');
});

it('varies the home section backgrounds over three light tones (item 15)', function () {
    directorHomeContent();
    $html = $this->get('/')->assertOk()->getContent();
    preg_match_all('/<section class="home-band"[^>]*data-band="(\d)"/', $html, $bands);

    expect(array_unique($bands[1]))->toContain('3');
    foreach (array_slice($bands[1], 1) as $index => $band) {
        expect($band)->not->toBe($bands[1][$index]);
    }

    $css = (string) file_get_contents(resource_path('css/concept.css'));
    expect($css)->toMatch("/\.home-band\[data-band='3'\] \{\s*background: linear-gradient\(180deg, color-mix\(in srgb, var\(--mid-from\)/");
});

it('puts «من نحن» in a soft light card with the vision and mission as quotations on the about page (item 20)', function () {
    $page = Page::query()->where('slug', Page::ABOUT)->first() ?? Page::factory()->create(['slug' => Page::ABOUT]);
    $page->forceFill(['is_published' => true, 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>تعريف الاختبار</p>']],
        ['type' => 'text', 'data' => ['heading' => __('ui.identity.vision'), 'body' => '<p>رؤية الاختبار</p>']],
        ['type' => 'text', 'data' => ['heading' => __('ui.identity.mission'), 'body' => '<p>رسالة الاختبار</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الأهداف', 'body' => '<p>هدف الاختبار</p>']],
        ['type' => 'text', 'data' => ['heading' => 'القيم', 'body' => '<p>قيمة الاختبار</p>']],
    ]])->save();
    ContentCache::flush();

    $html = $this->get(route('about'))->assertOk()->getContent();
    $intro = (string) str($html)->after('data-about-intro')->before('</section>');

    expect($intro)->toMatch('/class="tone-card about-who[^"]*" data-tone="soft" data-about-who/')
        ->and($intro)->toContain('تعريف الاختبار')
        ->and($intro)->toMatch('/data-about-quote="vision"\s+id="about-vision"/')->toContain('رؤية الاختبار')
        ->and($intro)->toMatch('/data-about-quote="mission"\s+id="about-mission"/')->toContain('رسالة الاختبار')
        // «من نحن» أولاً في الشيفرة، فهو يمين الحاسوب وأول الجوال، ثم الاقتباسان
        ->and(strpos($intro, 'data-about-who'))->toBeLessThan(strpos($intro, 'data-about-quotes'))
        ->and($intro)->not->toContain('هدف الاختبار');

    // بقية المقاطع بطاقات متعاقبة كما كانت لا ناعمة (قرار صفوان، البند ٣٢: الناعم لـ«من نحن» والكلمتين وحدها)، ولا تتكرر الرؤية والرسالة
    expect(substr_count($html, 'رؤية الاختبار'))->toBe(1)
        ->and($html)->toContain('data-stack-card')->not->toContain('data-tone="soft" data-stack-card')->toContain('هدف الاختبار')
        ->and(str($html)->after('data-about-strategy')->before('</section>')->toString())->toContain('data-tone="dark"');
});

/*
| البند ٢٨ من الإضافة: القائمة المنسدلة «عن الجمعية» بخمس مراسٍ بالترتيب، من بذرة لا تمس ما عدّله المحرر.
*/
it('adds the «عن الجمعية» anchors in order after the parent page, once, keeping what the admin edited (item 28)', function () {
    (new MenuSeeder)->run();
    $about = MenuItem::query()->whereNull('parent_id')->where('url', '/about')->firstOrFail();
    MenuItem::query()->where('parent_id', $about->id)->where('label', 'شهادة ترخيص الجمعية')->update(['label' => 'شهادة الترخيص من المحرر']);

    (new AboutMenuAnchorsSeeder)->run();
    (new AboutMenuAnchorsSeeder)->run();

    $children = MenuItem::query()->where('parent_id', $about->id)->orderBy('sort')->orderBy('id')->get(['label', 'url']);
    $anchors = $children->filter(fn (MenuItem $item): bool => str_contains((string) $item->url, '#'))->values();

    expect($anchors->map(fn (MenuItem $item): array => [$item->label, $item->url])->all())->toBe(AboutMenuAnchorsSeeder::ITEMS)
        // قبل العناصر القائمة في ترتيبها (والصفحة الأم أول القائمة المنسدلة)، ولا تكرار، وما عدّله المحرر باقٍ
        ->and($children->pluck('url')->slice(0, count(AboutMenuAnchorsSeeder::ITEMS))->values()->all())->toBe(array_column(AboutMenuAnchorsSeeder::ITEMS, 1))
        ->and($children->where('label', 'شهادة الترخيص من المحرر'))->toHaveCount(1)
        ->and($children->where('label', 'التوجه الاستراتيجي'))->toHaveCount(1);

    // والقائمة في الرأس بالترتيب نفسه، على الحاسوب وفي قائمة الجوال (وروابط صفحة غير منشورة لا تظهر في القائمة)
    Page::query()->where('slug', Page::ABOUT)->exists() || Page::factory()->create(['slug' => Page::ABOUT]);
    ContentCache::flush();
    $html = $this->get('/')->assertOk()->getContent();
    $desktop = (string) str($html)->after('data-main-nav')->before('id="mobile-menu"');
    $mobile = (string) str($html)->after('id="mobile-menu"');
    foreach ([$desktop, $mobile] as $menu) {
        $positions = array_map(fn (array $item): int|false => strpos($menu, ltrim($item[1], '/').'"'), AboutMenuAnchorsSeeder::ITEMS);
        expect($positions)->not->toContain(false);
        $sorted = $positions;
        sort($sorted);
        expect($positions)->toBe($sorted);
    }
});

it('leaves a strategic direction item the admin pointed elsewhere, adding the anchor beside it (item 28)', function () {
    $about = MenuItem::factory()->create(['label' => 'عن الجمعية', 'url' => '/about', 'parent_id' => null]);
    $edited = MenuItem::factory()->create(['parent_id' => $about->id, 'label' => 'التوجه الاستراتيجي', 'url' => '/governance', 'sort' => 4]);

    (new AboutMenuAnchorsSeeder)->run();

    expect($edited->fresh()->only(['label', 'url', 'sort']))->toBe(['label' => 'التوجه الاستراتيجي', 'url' => '/governance', 'sort' => 4])
        ->and(MenuItem::query()->where('parent_id', $about->id)->where('url', '/about#about-strategy')->count())->toBe(1);
});

it('gives each «عن الجمعية» anchor an existing id on the about page, with the strategic direction marked missing (item 28)', function () {
    $page = Page::query()->where('slug', Page::ABOUT)->first() ?? Page::factory()->create(['slug' => Page::ABOUT]);
    $page->forceFill(['is_published' => true, 'blocks' => [
        ['type' => 'text', 'data' => ['heading' => 'من نحن', 'body' => '<p>تعريف</p>']],
        ['type' => 'text', 'data' => ['heading' => __('ui.identity.vision'), 'body' => '<p>رؤية</p>']],
        ['type' => 'text', 'data' => ['heading' => __('ui.identity.mission'), 'body' => '<p>رسالة</p>']],
        ['type' => 'text', 'data' => ['heading' => 'الأهداف', 'body' => '<p>هدف</p>']],
        ['type' => 'text', 'data' => ['heading' => __('ui.about_anchors.values'), 'body' => '<p>قيمة</p>']],
    ]])->save();
    Initiative::factory()->create();
    ContentCache::flush();

    $html = $this->get(route('about'))->assertOk()->getContent();

    foreach (AboutMenuAnchorsSeeder::ITEMS as [, $url]) {
        expect($html)->toMatch('/\sid="'.preg_quote((string) parse_url($url, PHP_URL_FRAGMENT), '/').'"/');
    }
    expect($html)->toContain(__('content.about.strategy_heading'))->toContain(__('content.about.strategy_missing'));
});

/*
| البند ٣٠ من الإضافة: رأس «تواصل معنا» بفقرتي صفوان المعتمدتين بنصهما، ولا رقم ترخيص تحت «تواصل عبر الواتساب».
*/
it('shows the two approved paragraphs in the contact hero, and no licence number under the WhatsApp block (item 30)', function () {
    Setting::put('contact', ['whatsapp' => '966550556758']);

    $html = (string) $this->get('/contact')->assertOk()->getContent();
    $hero = (string) str($html)->after('data-page-hero')->before('</section>');
    preg_match_all('/<p class="page-hero-light-summary[^"]*" data-hero-summary>(.*?)<\/p>/s', $hero, $paragraphs);

    expect(array_map('trim', $paragraphs[1]))->toBe([
        'نرحب بتواصلكم مع جمعية إعجاز للدراسات والبحوث العلمية، ويسعدنا استقبال استفساراتكم، ومقترحاتكم، ومبادرات التعاون والشراكات العلمية والتعليمية.',
        'يسعد جمعية إعجاز للدراسات والبحوث العلمية تلقي استفساراتكم واقتراحاتكم حول برامجها ومبادراتها وخدماتها، وتصل إلى الفريق المختص عبر نموذج «الاستفسارات والاقتراحات» في صفحة التواصل، ليتابعها ويرد عليها.',
    ])
        ->and($hero)->not->toContain('【');

    $aside = (string) str($html)->after('data-whatsapp')->before('</aside>');
    expect($html)->toContain('data-whatsapp')
        ->and($aside)->not->toContain('1000723700')
        ->and((string) str($html)->after('data-header-licence')->before('</p>'))->toContain('1000723700');
});

// مراجعة المشرف على #157 (البند ٣٠): مقدمة «تواصل معنا» من الموقع السابق فقرتها الأولى نفسها في الرأس، فلا تتكرر تحته
it('shows the first contact paragraph once, in the hero, even with the old contact intro published', function () {
    $first = 'نرحب بتواصلكم مع جمعية إعجاز للدراسات والبحوث العلمية، ويسعدنا استقبال استفساراتكم، ومقترحاتكم، ومبادرات التعاون والشراكات العلمية والتعليمية.';
    Page::factory()->create([
        'slug' => 'contact',
        'is_published' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>'.$first.'</p>']]],
    ]);
    ContentCache::flush();

    $html = (string) $this->get('/contact')->assertOk()->getContent();

    expect(substr_count($html, $first))->toBe(1)
        ->and((string) str($html)->after('data-page-hero')->before('</section>'))->toContain($first);
});

/*
| مراجعة المشرف على #157 (4 نوفمبر، 10:58): رأس «مكتبة إعجاز» بصور تتبدل (البند ٢٥)، و«قياس الرضا» بطاقة دائمة في الخدمات التسع
| (البند ١٧ من الإضافة)، وقرارات صفوان في بطاقة المسجلين وصفة رئيس المجلس.
*/
it('picks the library hero images from the panel first, then the latest covers, at most four', function () {
    Storage::fake('public');
    Setting::put(HomeContent::KEY, ['page_heroes' => ['library' => 'settings/library-hero.jpg']]);
    foreach (range(1, 5) as $day) {
        LibraryItem::factory()->create(['thumbnail' => 'library/cover-'.$day.'.jpg', 'published_on' => now()->subDays($day)->toDateString()]);
    }
    LibraryItem::factory()->create(['thumbnail' => 'library/draft.jpg', 'is_published' => false, 'published_on' => now()->toDateString()]);

    $urls = LibraryHeroImages::urls();

    expect($urls)->toHaveCount(4)
        ->and($urls[0])->toBe(Storage::disk('public')->url('settings/library-hero.jpg'))
        ->and(array_slice($urls, 1))->toBe(array_map(fn (int $day): string => Storage::disk('public')->url('library/cover-'.$day.'.jpg'), [1, 2, 3]))
        ->and($urls)->not->toContain(Storage::disk('public')->url('library/draft.jpg'));

    $html = (string) $this->get('/library')->assertOk()->getContent();
    $hero = (string) str($html)->after('data-page-hero ')->before('</section>');
    expect($hero)->toContain('data-page-hero-rotator')->toContain('data-autoplay')
        ->and(substr_count($hero, 'data-carousel-item'))->toBe(4);
});

it('shows a single library hero image without rotation markup, and the neutral picture without any', function () {
    Storage::fake('public');
    $hero = fn (): string => (string) str((string) $this->get('/library')->assertOk()->getContent())->after('data-page-hero ')->before('</section>');

    expect(LibraryHeroImages::urls())->toBe([])
        ->and($hero())->not->toContain('data-page-hero-rotator')->toContain('data-page-hero-image');

    LibraryItem::factory()->create(['thumbnail' => 'library/only.jpg']);
    ContentCache::flush();
    // once() في القالب يحفظ الصور للطلب، والاختبار طلبان في عملية واحدة
    Once::flush();

    expect(LibraryHeroImages::urls())->toBe([Storage::disk('public')->url('library/only.jpg')])
        ->and($hero())->not->toContain('data-page-hero-rotator')->not->toContain('data-carousel-item')
        ->toContain('src="'.Storage::disk('public')->url('library/only.jpg').'"');
});

it('always shows the satisfaction card with no open survey: nine services and a link that opens', function () {
    $html = (string) $this->get('/services')->assertOk()->getContent();
    $grid = (string) str($html)->after('data-service-grid')->before('</ul>');

    expect($grid)->toContain('href="'.route('surveys.index').'"')->toContain(e(__('services.tiles.surveys')))
        ->and(substr_count($grid, 'class="service-card group"'))->toBe(9);

    $this->get(route('surveys.index'))->assertOk()->assertSee(__('surveys.public.none'))->assertSee('data-surveys-none', false);
});

it('names the registrants card as the directive does, and the chairman by the official association name', function () {
    expect(__('home.portal_stats.registrants'))->toBe('المسجلون في مسابقة البراهين')
        ->and(__('home.sections.chairman.title'))->toBe('رئيس مجلس إدارة جمعية إعجاز للدراسات والبحوث العلمية');
});

/*
| المدير التنفيذي عبر صفوان (4 نوفمبر 2026): «الأهداف» بعد «الرسالة» وقبل «القيم»، و«اللوائح والسياسات» تُخفى من «عن الجمعية».
*/
it('puts «الأهداف» between «الرسالة» and «القيم» on a menu seeded before, and hides «اللوائح والسياسات»', function () {
    (new MenuSeeder)->run();
    $about = MenuItem::query()->whereNull('parent_id')->where('url', '/about')->firstOrFail();
    // قائمة رابط العرض قبل التعديل: المراسي الخمس القديمة
    $old = array_values(array_filter(AboutMenuAnchorsSeeder::ITEMS, fn (array $item): bool => $item[1] !== '/about#about-goals'));
    $min = (int) MenuItem::query()->where('parent_id', $about->id)->min('sort');
    foreach ($old as $index => [$label, $url]) {
        MenuItem::query()->where('parent_id', $about->id)->where('url', $url)->exists()
            || MenuItem::query()->create(['parent_id' => $about->id, 'label' => $label, 'url' => $url, 'sort' => $min - 5 + $index, 'is_visible' => true]);
    }

    (new AboutMenuAnchorsSeeder)->run();
    (new AboutMenuAnchorsSeeder)->run();

    $visible = MenuItem::query()->where('parent_id', $about->id)->where('is_visible', true)->orderBy('sort')->orderBy('id')->pluck('url')->all();

    expect(array_slice($visible, 0, count(AboutMenuAnchorsSeeder::ITEMS)))->toBe(array_column(AboutMenuAnchorsSeeder::ITEMS, 1))
        ->and(MenuItem::query()->where('parent_id', $about->id)->where('url', '/about#about-goals')->count())->toBe(1)
        ->and(MenuItem::query()->where('parent_id', $about->id)->where('label', 'اللوائح والسياسات')->value('is_visible'))->toBeFalse();
});
