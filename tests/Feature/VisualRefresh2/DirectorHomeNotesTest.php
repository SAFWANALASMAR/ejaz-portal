<?php

use App\Enums\LibrarySection;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\IconTones;
use App\Support\MenuIcon;
use App\Support\MenuLink;
use Database\Seeders\MenuSeeder;

/*
| ملاحظات المدير التنفيذي على الرئيسية (31 أكتوبر 01:28) وقرارا صفوان (01:38): القائمة مربعات بأيقونة متدرجة فوق الكلمة بترتيب
| الألوان، وبطاقات «رؤيتنا» و«قيمنا» و«مبادراتنا» المتدرجة بدل بطاقة الجوائز، و«خدماتنا» بتدرج لكل بطاقة، والمقاطع بدرجات متتالية،
| والأخبار بتدرج يشتد، وشريطا المرئيات وأغلفة الكتب، وبطاقة «تابعنا على X» من الإعدادات وحدها، وأيقونات روابط التذييل.
*/

beforeEach(fn () => (new MenuSeeder)->run());

function navTones(string $html): array
{
    preg_match('/<nav data-main-nav.*?<\/nav>/s', $html, $nav);
    preg_match_all('/class="nav-link[^"]*">\s*<span data-icon-tile data-variant="gradient" data-tone="(\w+)"/', $nav[0] ?? '', $tones);

    return $tones[1];
}

it('gives every desktop menu item a gradient icon tile above the word, in the colour order, and marks the active one', function () {
    $html = $this->get('/')->assertOk()->getContent();
    $tones = navTones($html);

    expect($tones)->not->toBeEmpty()
        ->and($tones)->toBe(array_map(fn (int $index): string => IconTones::at($index), array_keys($tones)));

    preg_match('/<nav data-main-nav.*?<\/nav>/s', $html, $nav);
    expect($nav[0])->toMatch('/aria-current="page"\s+class="nav-link/');
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

it('shows vision, values and initiatives as gradient cards instead of the prize card', function () {
    Initiative::factory()->create(['name' => 'مبادرة قيم', 'is_published' => true]);

    $this->get('/')->assertOk()
        ->assertSeeInOrder(['data-identity-card="initiatives"', __('ui.identity.our_initiatives'), 'مبادرة قيم', __('ui.identity.all_initiatives')], false)
        ->assertSee('class="identity-card group" data-tone="green"', false)
        ->assertDontSee('data-prize-card', false);
});

it('tints each service card from its tile colour, in order', function () {
    $html = $this->get('/')->getContent();
    preg_match_all('/data-tone="(\w+)" data-service-card/', $html, $tones);

    expect($tones[1])->not->toBeEmpty()
        ->and($tones[1])->toBe(array_map(fn (int $index): string => IconTones::at($index), array_keys($tones[1])));
});

it('runs the video and book strips from the library, with a hidden copy for the loop', function () {
    LibraryItem::factory()->video()->create(['title' => 'مقطع الشريط', 'thumbnail' => 'library/thumbs/a.jpg']);
    LibraryItem::factory()->create(['title' => 'كتاب الشريط', 'section' => LibrarySection::Books, 'thumbnail' => 'library/thumbs/b.jpg']);

    $html = $this->get('/')->assertOk()
        ->assertSee('data-media-strip="videos"', false)
        ->assertSee('data-media-strip="books"', false)
        ->assertSee('مقطع الشريط')->assertSee('كتاب الشريط')
        ->assertSee('data-ticker-toggle="home-strip-videos-track"', false)
        ->assertDontSee('id="home-updates"', false)
        ->getContent();

    expect(substr_count($html, 'data-strip-copy aria-hidden="true" inert'))->toBe(2)
        ->and($html)->not->toContain('youtube-nocookie.com/embed');
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

    expect(substr_count($links[0], 'data-icon-tile'))->toBe(substr_count($links[0], '<li>'));
});
