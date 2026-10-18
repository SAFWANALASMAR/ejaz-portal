<?php

use App\Models\MenuItem;
use App\Models\Page;
use Database\Seeders\ContentSeeder;
use Database\Seeders\MenuSeeder;

/*
| ملاحظات التجربة الشاملة (18 أكتوبر 2026): لا يظهر في الرأس ولا في قائمة الجوال ولا في التذييل رابط إلى صفحة
| محتوى غير منشورة (الخلل 2)، والتذييل يحترم «فتح في تبويب جديد» لعنصر القائمة (الخلل 10).
*/

/**
 * روابط الصفحة كلها: الرأس وقائمة الجوال والتذييل.
 *
 * @return list<string>
 */
function pageHrefs(string $html): array
{
    preg_match_all('/<a\s[^>]*href="([^"]*)"/', $html, $matches);

    return $matches[1];
}

/**
 * وسم الرابط في التذييل بعنوانه.
 */
function footerLink(string $html, string $label): string
{
    $footer = (string) str($html)->after('<footer');

    preg_match('#<a\s[^>]*>\s*'.preg_quote($label, '#').'\s*</a>#u', $footer, $match);

    return $match[0] ?? '';
}

it('hides every menu and footer link to an unpublished page, whatever its slug', function (Closure|string $url, string $slug) {
    $url = value($url);
    Page::factory()->unpublished()->create(['slug' => $slug]);
    MenuItem::factory()->create(['label' => 'صفحة مسودة', 'url' => $url, 'sort' => 1]);
    MenuItem::factory()->create(['label' => 'الأخبار', 'url' => '/media/news', 'sort' => 2]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('صفحة مسودة')->toContain('الأخبار')
        ->and(pageHrefs($html))->not->toContain(url($url));
})->with([
    ['/about', 'about'],
    ['/privacy', 'privacy'],
    ['/pages/vision', 'vision'],
    ['/about#board', 'about'],
    ['/pages/vision?x=1', 'vision'],
    // رابط كامل على مضيف البوابة نفسه داخلي كذلك
    'full link to the about page' => [fn () => url('/about'), 'about'],
    'full link to a page' => [fn () => url('/pages/vision'), 'vision'],
    'full link with www' => [fn () => preg_replace('#://#', '://www.', url('/about'), 1), 'about'],
    'path in capitals' => ['/About', 'about'],
    'host and slug in capitals' => [fn () => str_replace('://', '://WWW.', url('/pages/Vision')), 'vision'],
]);

it('reads a full link on another host as an outside link, whatever its path', function () {
    MenuItem::factory()->create(['label' => 'موقع آخر', 'url' => 'https://other.example/about']);

    $this->get('/')->assertOk()->assertSee('موقع آخر');
});

it('hides a link to a page that does not exist', function () {
    MenuItem::factory()->create(['label' => 'صفحة غائبة', 'url' => '/pages/missing']);

    $this->get('/')->assertOk()->assertDontSee('صفحة غائبة');
});

it('shows the link as soon as the page is published, without waiting for the cached menu', function () {
    $page = Page::factory()->unpublished()->create(['slug' => 'vision']);
    MenuItem::factory()->create(['label' => 'الرؤية', 'url' => '/pages/vision']);

    $this->get('/')->assertDontSee('الرؤية');

    $page->update(['is_published' => true]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('الرؤية')
        ->and(pageHrefs($html))->toContain(url('/pages/vision'))
        ->and(footerLink($html, 'الرؤية'))->not->toBe('');
});

it('keeps the links that are not content pages: sections, intro pages and outside links', function () {
    MenuItem::factory()->create(['label' => 'التواصل', 'url' => '/pages/contact', 'sort' => 1]);
    MenuItem::factory()->create(['label' => 'الحوكمة', 'url' => '/governance', 'sort' => 2]);
    MenuItem::factory()->create(['label' => 'المتجر', 'url' => 'https://ejaz.org.sa', 'sort' => 3]);

    $this->get('/')->assertOk()->assertSee('التواصل')->assertSee('الحوكمة')->assertSee('المتجر');
});

it('drops the unpublished children of a dropdown, and the dropdown itself when none is left', function () {
    $parent = MenuItem::factory()->create(['label' => 'عن الجمعية', 'url' => '/about', 'sort' => 1]);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'التوجه الاستراتيجي', 'url' => '/about']);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'الرؤية', 'url' => '/pages/vision']);

    $this->get('/')->assertOk()->assertDontSee('عن الجمعية')->assertDontSee('التوجه الاستراتيجي')->assertDontSee('الرؤية');
});

it('keeps a dropdown whose own page is unpublished for its remaining children, with no footer link to that page', function () {
    $parent = MenuItem::factory()->create(['label' => 'عن الجمعية', 'url' => '/about', 'sort' => 1]);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'عن الجمعية', 'url' => '/about', 'sort' => 1]);
    MenuItem::factory()->create(['parent_id' => $parent->id, 'label' => 'اللوائح والسياسات', 'url' => '/governance?category=policies', 'sort' => 2]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('aria-controls="menu-'.$parent->id.'"')
        ->toContain('اللوائح والسياسات')
        ->and(pageHrefs($html))->not->toContain(url('/about'))
        ->and(footerLink($html, 'عن الجمعية'))->toBe('');
});

it('shows no link to a 404 page from the seeded menu while the about page is a draft', function () {
    $this->seed([ContentSeeder::class, MenuSeeder::class]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(pageHrefs($html))->not->toContain(url('/about'))->not->toContain(url('/privacy'));

    Page::query()->where('slug', Page::ABOUT)->sole()->update(['is_published' => true]);

    expect(pageHrefs((string) $this->get('/')->getContent()))->toContain(url('/about'));
});

it('opens a footer link in a new tab when its menu item says so, and keeps the others in the same tab', function () {
    MenuItem::factory()->create(['label' => 'المتجر', 'url' => '/store', 'opens_in_new_tab' => true, 'sort' => 1]);
    MenuItem::factory()->create(['label' => 'الحوكمة', 'url' => '/governance', 'sort' => 2]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(footerLink($html, 'المتجر'))->toContain('target="_blank"')->toContain('rel="noopener"')->not->toContain('wire:navigate')
        ->and(footerLink($html, 'الحوكمة'))->not->toContain('target=')->toContain('wire:navigate');
});
