<?php

use App\Actions\Settings\UpdateHomeContent;
use App\Enums\LibrarySection;
use App\Enums\RoleName;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\MenuIcon;
use App\Support\MenuLink;
use Database\Seeders\MenuSeeder;

/*
| ملاحظات المدير التنفيذي على الرئيسية (31 أكتوبر 01:28) وقرارا صفوان (01:38): القائمة مربعات بأيقونة متدرجة فوق الكلمة بترتيب
| الألوان، وبطاقات «رؤيتنا» و«قيمنا» و«مبادراتنا» المتدرجة بدل بطاقة الجوائز، و«خدماتنا» بتدرج لكل بطاقة، والمقاطع بدرجات متتالية،
| والأخبار بتدرج يشتد، وشريطا المرئيات وأغلفة الكتب، وبطاقة «تابعنا على X» من الإعدادات وحدها، وأيقونات روابط التذييل.
*/

beforeEach(fn () => (new MenuSeeder)->run());

it('shows the seven menu items as capsules with an icon tile beside the word, and marks the active one', function () {
    $html = $this->get('/')->assertOk()->getContent();
    preg_match('/<nav data-main-nav.*?<\/nav>/s', $html, $nav);
    preg_match_all('/class="nav-capsule">\s*<span data-icon-tile data-variant="gradient"[^>]*>.*?<span class="truncate">([^<]+)<\/span>/s', $nav[0], $items);

    // أمر التصور النهائي، النسخة 63 (البند R3): سبعة عناصر فقط بهذا الترتيب
    expect($items[1])->toBe(['الرئيسية', 'عن الجمعية', 'مكتبة إعجاز', 'المركز الإعلامي', 'الحوكمة', 'الخدمات الإلكترونية', 'تواصل معنا'])
        ->and($nav[0])->toMatch('/aria-current="page"\s+class="nav-capsule"/')
        ->and($nav[0])->not->toContain('data-shade');
});

it('keeps the icons beside the words in the mobile menu', function () {
    $html = $this->get('/')->getContent();
    preg_match('/<div id="mobile-menu".*?<\/nav>/s', $html, $menu);

    expect(substr_count($menu[0], 'data-icon-tile'))->toBeGreaterThan(0)
        ->and($menu[0])->toContain('flex items-center gap-2.5');
});

it('maps a menu link to the icon of its section, and an outside link to the generic one', function () {
    expect(MenuIcon::for(new MenuLink(1, 'x', '/library', false, collect())))->toBe('book-open')
        ->and(MenuIcon::for(new MenuLink(1, 'x', '/', false, collect())))->toBe('home')
        ->and(MenuIcon::for(new MenuLink(1, 'x', 'https://ejaz.org.sa', false, collect())))->toBe(MenuIcon::FALLBACK);
});

// أمر التصور النهائي، النسخة 63 (البندان R6 وR11): خمس بطاقات ملونة بالترتيب، داكنة ثم فاتحة بالتعاقب
it('shows the five home cards in order as alternating tone cards instead of the prize card', function () {
    Initiative::factory()->create(['name' => 'مبادرة قيم', 'is_published' => true]);

    $html = (string) str($this->get('/')->assertOk()->getContent())->after('id="home-identity"')->before('data-marquee-copy');
    preg_match_all('/class="tone-card home-card[^"]*" data-tone="(\w+)" data-identity-card="(\w+)"/', $html, $cards);

    expect($cards[2])->toBe(['vision', 'goals', 'values', 'principles', 'initiatives'])
        ->and($cards[1])->toBe(['dark', 'light', 'dark', 'light', 'dark'])
        ->and($html)->toContain('مبادرة قيم')
        ->and($html)->toContain('【ناقص: أهداف الجمعية المعتمدة】')
        ->and($html)->toContain('【ناقص: مبادئ الجمعية المعتمدة】')
        ->and($html)->not->toContain('data-prize-card');
});

it('takes the goals, principles and section headings from the home content panel', function () {
    app(UpdateHomeContent::class)->handle(userWithRole(RoleName::ContentEditor), [
        'identity' => ['goals' => 'هدف اختباري', 'principles' => 'مبدأ اختباري'],
        'headings' => ['services' => 'خدمات اختبارية', 'partners' => 'شركاء اختباريون'],
        'values' => ['items' => array_fill(0, 4, [])],
    ]);
    Partner::factory()->create(['name' => 'شريك الشريط']);

    $this->get('/')->assertOk()
        ->assertSee('هدف اختباري')->assertSee('مبدأ اختباري')
        ->assertSee('خدمات اختبارية')->assertSee('شركاء اختباريون')
        ->assertDontSee('【ناقص: أهداف الجمعية المعتمدة】');
});

it('runs the services as one moving row with programmes, competitions, initiatives, partners and the survey card', function () {
    $html = (string) str($this->get('/')->getContent())->after('id="home-services-track"')->before('data-marquee-copy');
    preg_match_all('/data-service-card="(\w+)"/', $html, $keys);

    // «قياس الرضا» ظاهرة دائماً، بلا استبيان مفتوح أيضاً (إضافة توجيه المدير، البند ١٧)
    expect($keys[1])->toBe(['services', 'membership', 'governance', 'competitions', 'initiatives', 'library', 'media', 'partners', 'surveys'])
        ->and($html)->toContain(__('home.shortcuts_final.surveys.title'))
        ->and($html)->toContain('href="'.route('surveys.index').'"')
        ->and($html)->toContain(__('home.shortcuts_final.partners.title'))
        ->and($html)->toContain(__('home.shortcuts_final.initiatives.text'));
});

it('runs the video and book strips from the library, with a hidden copy for the loop', function () {
    // ستة مقاطع وثمانية كتب: ما يملأ عرض 1440 فيتحرك (والأقل صف ثابت — الاختبار التالي)
    LibraryItem::factory()->video()->create(['title' => 'مقطع الشريط', 'thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->video()->count(5)->create(['thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->create(['title' => 'كتاب الشريط', 'section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);
    LibraryItem::factory()->count(7)->create(['section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);

    $html = $this->get('/')->assertOk()
        ->assertSee('data-media-strip="videos"', false)
        ->assertSee('data-media-strip="books"', false)
        ->assertSee('مقطع الشريط')->assertSee('كتاب الشريط')
        ->assertSee('data-ticker-toggle="home-strip-videos-track"', false)
        ->assertDontSee('id="home-updates"', false)
        ->getContent();

    $strips = (string) str($html)->after('data-home-strips')->before('</section>');

    // نسخة ثانية خاملة لكل شريط، والمقاطع مرقّمة (x-video-number)
    expect(substr_count($strips, 'data-marquee-copy aria-hidden="true" inert'))->toBe(2)
        ->and($strips)->toContain('data-video-number')
        ->and($html)->not->toContain('youtube-nocookie.com/embed');
});

// ما لا يملأ عرض 1440 يتحرك أيضاً (إضافة توجيه المدير، 3 نوفمبر 2026، البند ١١: «ما كان يتحرك يبقى يتحرك»؛ وكان صفاً ثابتاً
// بمراجعة المشرف في 2 نوفمبر): يتكرر في المسار حتى يملأه، والظاهر لقارئ الشاشة ولوحة المفاتيح نسخة واحدة
it('moves a single partner too, repeated to fill the strip and read once, and six partners with one copy', function () {
    Partner::factory()->create(['name' => 'الشريك الوحيد']);

    $html = $this->get('/')->assertOk()->getContent();
    $section = (string) str($html)->after('aria-labelledby="home-partners"')->before('</section>');

    // ستة أضعاف تملأ العرض: المجموعة الظاهرة ثم إحدى عشرة نسخة مخفية (المسار ضعف ما يملأ العرض)
    expect($section)->toContain('data-marquee data-direction="horizontal" data-loop')
        ->and($section)->not->toContain('data-marquee-static')
        ->and(substr_count($section, 'data-marquee-copy aria-hidden="true" inert'))->toBe(11)
        ->and(substr_count((string) str($section)->before('data-marquee-copy'), 'الشريك الوحيد'))->toBe(1)
        ->and($section)->toContain('data-ticker-toggle="home-partners-track"');

    Partner::factory()->count(5)->create();
    ContentCache::flush();

    $section = (string) str($this->get('/')->getContent())->after('aria-labelledby="home-partners"')->before('</section>');
    expect($section)->toContain('data-marquee data-direction="horizontal" data-loop')
        ->and(substr_count($section, 'data-marquee-copy aria-hidden="true" inert'))->toBe(1)
        ->and($section)->toContain('data-ticker-toggle="home-partners-track"');
});

it('moves fewer than six videos or eight books too, each read once', function () {
    LibraryItem::factory()->video()->count(5)->create(['thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->count(3)->create(['section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);

    $strips = (string) str($this->get('/')->assertOk()->getContent())->after('data-home-strips')->before('</section>');
    $videos = (string) str($strips)->after('data-media-strip="videos"')->before('data-media-strip="books"');
    $books = (string) str($strips)->after('data-media-strip="books"');

    // خمسة مقاطع: ضعفان (ثلاث نسخ مخفية)، وثلاثة كتب: ثلاثة أضعاف (خمس نسخ مخفية)
    expect($strips)->not->toContain('data-marquee-static')
        ->and(substr_count($strips, 'data-ticker-toggle'))->toBe(2)
        ->and(substr_count($videos, 'data-marquee-copy aria-hidden="true" inert'))->toBe(3)
        ->and(substr_count($books, 'data-marquee-copy aria-hidden="true" inert'))->toBe(5)
        ->and(substr_count((string) str($videos)->before('data-marquee-copy'), 'class="tone-card media-tile group"'))->toBe(5)
        ->and(substr_count((string) str($books)->before('data-marquee-copy'), 'class="tone-card media-tile group"'))->toBe(3);
});

// ملاحظة المدير ٢٤: الشريطان «تحت خدماتنا» مباشرة، ثم الإعلانات والأخبار، والدرجات تبقى متعاقبة
it('puts the video and book strips directly under the services, before the announcements and news', function () {
    LibraryItem::factory()->video()->create(['thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->create(['section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);
    NewsArticle::factory()->create();
    ContentCache::flush();

    $html = $this->get('/')->assertOk()->getContent();
    // مقاطع الرئيسية بالترتيب: نص كل مقطع من وسم <section class="home-band"> إلى الذي يليه
    $sections = array_slice(preg_split('/(?=<section class="home-band")/', $html), 1);
    $at = fn (string $marker): int|false => array_search(true, array_map(fn (string $section): bool => str_contains($section, $marker), $sections), true);
    $band = fn (int $index): string => (string) str($sections[$index])->after('data-band="')->before('"');

    $services = $at('aria-labelledby="home-shortcuts"');
    $strips = $at('data-home-strips');
    $news = $at('id="home-news-track"');

    expect($services)->not->toBeFalse()
        ->and($strips)->toBe($services + 1)
        ->and($news)->toBe($strips + 1)
        ->and($band($strips))->not->toBe($band($services))
        ->and($band($news))->not->toBe($band($strips));
});

it('shows the follow-us-on-X card only when the official X account is set, opening it in a new tab', function () {
    $this->get('/')->assertOk()->assertDontSee('data-x-card', false);

    Setting::put('contact', ['social' => ['x' => 'https://x.com/ejaz_sa']]);
    ContentCache::flush();

    $this->get('/')->assertOk()
        ->assertSee('data-x-card', false)
        ->assertSee('@ejaz_sa')
        ->assertSee('href="https://x.com/ejaz_sa" target="_blank" rel="noopener noreferrer" data-x-follow', false)
        ->assertSee(__('ui.strips.x_follow'));
});

it('ignores an X setting that is not an x.com or twitter.com https link', function () {
    Setting::put('contact', ['social' => ['x' => 'https://evil.example/ejaz']]);
    ContentCache::flush();

    $this->get('/')->assertOk()->assertDontSee('data-x-card', false);
});

it('puts the home sections on alternating bands with no repeated shade back to back', function () {
    Initiative::factory()->create(['is_published' => true]);
    $html = $this->get('/')->getContent();
    preg_match_all('/class="home-band[^"]*"[^>]*data-band="(\d)"/', $html, $bands);

    expect(count($bands[1]))->toBeGreaterThanOrEqual(2);
    foreach (array_slice($bands[1], 1) as $index => $band) {
        expect($band)->not->toBe($bands[1][$index]);
    }
});

it('gives each footer quick link a coloured icon tile', function () {
    $html = $this->get('/')->getContent();
    preg_match('/aria-labelledby="footer-links".*?<\/nav>/s', $html, $links);

    // ومربع العنوان الكبير بجانب «روابط سريعة» فوق مربعات الروابط
    expect(substr_count($links[0], 'data-icon-tile'))->toBe(substr_count($links[0], '<li') + 1);
});
