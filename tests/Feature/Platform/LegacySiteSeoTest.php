<?php

use App\Models\LibraryItem;
use App\Models\Page;
use App\Support\LegacyPaths;
use Illuminate\Support\Facades\Storage;

/*
| ما يلزم لإطلاق الموقع بدل السابق (docs/launch-plan.md): الوصف والأيقونة المفضلة والرابط المعتمد
| وصفحة 404، وروابط ووردبريس القديمة، وصفحات المقدمة، ومقطع الرئيسية.
*/

beforeEach(function () {
    showLegacyHomeSections();
});

it('describes every page by default with the approved short description, with a canonical link and the favicons', function () {
    $html = (string) $this->get('/contact')->assertOk()->getContent();

    expect($html)->toContain('<meta name="description" content="'.__('site.description').'">')
        ->toContain('<link rel="canonical" href="'.url('/contact').'">')
        ->toContain('href="'.asset('favicon.ico').'"')
        ->toContain('href="'.asset('apple-touch-icon.png').'"');

    foreach (['favicon.ico', 'favicon-32x32.png', 'apple-touch-icon.png', 'icon-192.png'] as $file) {
        expect(filesize(public_path($file)))->toBeGreaterThan(0);
    }

    expect(__('site.description'))->toBe('جمعية تعليمية متخصصة في تعليم القرآن الكريم والسنة النبوية وبيان وجوه الإعجاز فيهما');
});

it('takes the title and description of a page from its own data', function (string $slug, string $path) {
    Page::factory()->create(['slug' => $slug, 'title' => 'عنوان الصفحة', 'summary' => 'ملخص الصفحة', 'is_published' => true]);

    $this->get($path)->assertOk()
        ->assertSee('<title>عنوان الصفحة | '.__('site.short_name').'</title>', false)
        ->assertSee('<meta name="description" content="ملخص الصفحة">', false)
        ->assertSee('<link rel="canonical" href="'.url($path).'">', false);
})->with([
    'a page by its slug' => ['vision', '/pages/vision'],
    'the privacy policy' => ['privacy', '/privacy'],
]);

it('shows the Arabic 404 page with the association identity and no indexing', function () {
    $this->get('/no-such-page')->assertNotFound()
        ->assertSee('<html lang="ar" dir="rtl">', false)
        ->assertSee(__('errors.404.title'))
        ->assertSee('noindex', false)
        ->assertSee(__('site.name'));
});

it('sends old WordPress paths with no registered redirect to the nearest section', function (string $path, ?string $target) {
    expect(LegacyPaths::nearest($path))->toBe($target);
})->with([
    ['/wp-content/uploads/2021/10/anything.jpg', '/'],
    ['/wp-content/uploads/2026/01/'.rawurlencode('وثيقة').'.pdf', '/governance'],
    ['/wp-content/uploads/2026/08/E3jaz-99.mp3', '/library/audio'],
    ['/wp-content/themes/hub/style.css', '/'],
    ['/category/news/', '/media/news'],
    ['/tag/x/', '/media/news'],
    ['/feed/', '/media/news'],
    ['/some-post/feed/', '/media/news'],
    ['/2021/10/07/hello-world/', '/media/news'],
    ['/wp-admin/', null],
    ['/wp-login.php', null],
    ['/about-us', null],
]);

it('sends the old WordPress query links on the home page permanently to a section, and keeps the home page otherwise', function () {
    $this->get('/?p=12')->assertStatus(301)->assertRedirect('/media/news');
    $this->get('/?page_id=17')->assertStatus(301)->assertRedirect('/');
    $this->get('/?s=test')->assertStatus(301)->assertRedirect('/');
    $this->get('/?utm_source=x')->assertOk();
});

it('shows a published intro page above its built template, hides a draft, and sends its own address there', function (string $slug, string $url) {
    $page = Page::factory()->create(['slug' => $slug, 'title' => 'مقدمة', 'is_published' => false,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص المقدمة '.$slug.'</p>']]]]);

    $this->get($url)->assertOk()->assertDontSee('نص المقدمة '.$slug);

    $page->update(['is_published' => true]);

    $this->get($url)->assertOk()->assertSee('نص المقدمة '.$slug);
    $this->get('/pages/'.$slug)->assertStatus(301)->assertRedirect(url($url));
    $this->get('/sitemap.xml')->assertDontSee('/pages/'.$slug);
})->with([
    ['contact', '/contact'],
    ['inquiries', '/contact'],
    ['assembly-membership', '/services/assembly-membership'],
    ['jobs', '/services/jobs'],
    ['volunteering', '/services/volunteering'],
    ['reports', '/governance?category=annual-reports'],
]);

// «تواصل معنا» (البند ٣٠ ومراجعة المشرف على #157): مقدمة نصها في الرأس (فقرته الأولى المعتمدة) لا تتكرر تحته، ومقدمة بنص آخر تظهر
it('hides the contact intro only when its text is already in the hero, and shows a different one above the template', function () {
    $first = (string) str(__('contact.hero_text'))->before('
')->trim();
    $page = Page::factory()->create(['slug' => 'contact', 'is_published' => true,
        'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>'.$first.'</p>']]]]);

    $html = (string) $this->get('/contact')->assertOk()->getContent();
    expect(substr_count($html, $first))->toBe(1)
        ->and((string) str($html)->after('data-page-hero')->before('</section>'))->toContain($first);

    $page->update(['blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>نص مقدمة مختلف عن الرأس</p>']]]]);

    $html = (string) $this->get('/contact')->assertOk()->getContent();
    expect($html)->toContain('نص مقدمة مختلف عن الرأس')
        ->and(strpos($html, 'نص مقدمة مختلف عن الرأس'))->toBeGreaterThan(strpos($html, 'data-page-hero'))
        ->and(strpos($html, 'نص مقدمة مختلف عن الرأس'))->toBeLessThan(strpos($html, 'data-contact-form'));
});

it('shows the image alt text of a block, and an empty one for a decorative image', function () {
    Storage::fake('public');
    Page::factory()->create(['slug' => 'privacy', 'is_published' => true, 'blocks' => [
        ['type' => 'image', 'data' => ['picture' => 'pages/a.webp', 'alt' => 'قاعة', 'caption' => null]],
        ['type' => 'image', 'data' => ['picture' => 'pages/b.webp', 'alt' => '', 'caption' => 'وصف ظاهر']],
        ['type' => 'cards', 'data' => ['heading' => null, 'items' => [['title' => 'هدف', 'picture' => 'pages/c.webp']]]],
    ]]);

    $html = (string) $this->get('/privacy')->assertOk()->getContent();

    expect($html)->toContain('pages/a.webp" alt="قاعة"')
        ->toContain('pages/b.webp" alt=""')
        ->toContain('pages/c.webp" alt=""');
});

it('embeds the home video from the settings, and the hero image with its alt text', function () {
    Storage::fake('public');
    showLegacyHomeSections(['video_url' => 'https://youtube.com/watch?v=WaSvMAl4zPw', 'hero_image' => 'settings/hero.webp', 'hero_image_alt' => 'قاعة']);

    $this->get('/')->assertOk()
        ->assertSee('youtube-nocookie.com/embed/WaSvMAl4zPw', false)
        ->assertSee('settings/hero.webp" alt="قاعة"', false);

    showLegacyHomeSections(['video_url' => 'https://evil.example/watch?v=WaSvMAl4zPw']);

    $this->get('/')->assertOk()->assertDontSee('WaSvMAl4zPw');
});

it('shows a library section with nothing published as coming soon', function () {
    LibraryItem::factory()->create(['section' => 'books', 'is_published' => false]);

    $this->get('/library/books')->assertOk()->assertSee(__('library.list.soon'));
    $this->get('/library/research')->assertOk()->assertSee(__('library.list.soon'));
});
