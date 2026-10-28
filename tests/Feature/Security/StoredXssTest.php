<?php

use App\Enums\RoleName;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\BoardMember;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\MenuItem;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Program;
use App\Models\Setting;
use App\Models\User;
use App\Support\ContentCache;

/*
| XSS مخزّن (الاختبار الأمني 0.14.0): حمولة في كل حقل تحرره اللوحة ويظهر للعامة، وفي اسم المستخدم الذي يراه المسؤول،
| ثم الصفحة التي تعرضه. لا يظهر الوسم خاماً في أي منها، ويظهر نصه مرمَّزاً في صفحة السجل نفسه (شاهداً على أن الحقل
| عُرض فعلاً). والنص المنسق يُنقّى عند العرض (RichText)، وحقول الروابط لا تقبل javascript: ولا يظهر في href.
*/

const XSS = '"><img src=x onerror=alert(1)><script>alert(1)</script>';

function assertNoRawXss(string $html, string $where): void
{
    expect($html)->not->toContain('<img src=x onerror', $where)
        ->and($html)->not->toContain('<script>alert(1)', $where)
        ->and($html)->not->toMatch('/href="\s*javascript:/i', $where);
}

beforeEach(function () {
    // صفحة «عن الجمعية» تعرض مجلس الإدارة، والقائمة والتذييل في كل صفحة
    Page::factory()->create(['slug' => 'about', 'title' => 'عن الجمعية']);
});

it('escapes an admin field everywhere it reaches the public site', function (Closure $make) {
    /** @var array{0: string, 1: list<string>} $pages الصفحة التي يظهر فيها نصه مرمَّزاً، وصفحات أخرى تعرضه */
    [$own, $others] = $make();
    ContentCache::flush();

    $html = $this->get($own)->assertOk()->getContent();
    assertNoRawXss($html, $own);
    expect($html)->toContain(e(XSS), $own);

    foreach ($others as $url) {
        assertNoRawXss($this->get($url)->assertOk()->getContent(), $url);
    }
})->with([
    'page title, summary, block heading and card' => fn () => [route('pages.show', Page::factory()->create([
        'title' => XSS, 'summary' => XSS, 'blocks' => [
            ['type' => 'text', 'data' => ['heading' => XSS, 'body' => '<p>نص</p>']],
            ['type' => 'cards', 'data' => ['heading' => XSS, 'items' => [['title' => XSS, 'text' => XSS]]]],
        ],
    ])->slug), []],
    'news title and summary' => fn () => [route('media.news.show', NewsArticle::factory()->create(['title' => XSS, 'summary' => XSS])->slug), ['/', '/media/news', '/media']],
    'library title, author and description' => function () {
        $item = LibraryItem::factory()->create(['title' => XSS, 'author' => XSS, 'description' => XSS]);

        return [route('library.show', ['section' => $item->section, 'slug' => $item->slug]), ['/library']];
    },
    'program title, summary and description' => fn () => [route('programs.show', Program::factory()->create(['title' => XSS, 'summary' => XSS, 'description' => XSS])->slug), ['/programs']],
    'initiative name and summary' => fn () => [route('initiatives.show', Initiative::factory()->create(['name' => XSS, 'summary' => XSS])->slug), ['/initiatives', '/']],
    'album title and description' => fn () => [route('media.albums.show', Album::factory()->create(['title' => XSS, 'description' => XSS])->slug), ['/media/albums']],
    'opening title, summary, description and field label' => fn () => [route('services.openings.show', Opening::factory()->create([
        'title' => XSS, 'summary' => XSS, 'description' => XSS,
    ])->slug), ['/services']],
    'competition title, description, terms and prizes' => fn () => [route('competitions.show', Competition::factory()->create([
        'title' => XSS, 'description' => XSS, 'terms' => XSS, 'prizes' => XSS,
    ])->slug), ['/competitions']],
    'governance title and description' => fn () => [route('governance.show', Document::factory()->create(['title' => XSS, 'description' => XSS])->slug), ['/governance']],
    'board member name and position' => function () {
        BoardMember::factory()->create(['name' => XSS, 'position' => XSS]);

        return ['/about', []];
    },
    'partner name' => function () {
        Partner::factory()->create(['name' => XSS]);

        return ['/media/partners', ['/']];
    },
    'menu label' => function () {
        MenuItem::factory()->create(['label' => XSS]);

        return ['/', ['/about', '/contact']];
    },
    'announcement title' => function () {
        Announcement::factory()->create(['title' => XSS]);
        showLegacyHomeSections();

        return ['/', []];
    },
]);

it('strips script, event handlers, styles and javascript links from rich text at render time', function () {
    $page = Page::factory()->create(['blocks' => [['type' => 'text', 'data' => ['heading' => 'نص', 'body' => '<p style="background:url(x)">نص <a href="javascript:alert(1)">رابط</a></p><script>alert(1)</script><img src=x onerror=alert(1)><iframe src="https://evil.example"></iframe><a href="//evil.example">خارجي</a>']]]]);

    $html = $this->get(route('pages.show', $page->slug))->assertOk()->getContent();

    assertNoRawXss($html, 'rich text');
    expect($html)->not->toContain('onerror')
        ->and($html)->not->toContain('<iframe src="https://evil')
        ->and($html)->not->toContain('href="//evil.example"')
        ->and($html)->not->toContain('background:url(x)')
        ->and($html)->toContain('رابط');
});

it('never renders a javascript or protocol-relative link saved in a link field', function (string $url) {
    Partner::factory()->create(['url' => $url]);
    Announcement::factory()->create(['url' => $url]);
    MenuItem::factory()->create(['url' => $url, 'label' => 'رابط']);
    Setting::put('contact', ['social' => ['x' => $url, 'instagram' => $url], 'map_url' => $url]);
    showLegacyHomeSections();
    ContentCache::flush();

    foreach (['/', '/media/partners', '/contact'] as $path) {
        $html = $this->get($path)->assertOk()->getContent();

        expect($html)->not->toMatch('/href="\s*(javascript|data|vbscript):/i', $path)
            ->and($html)->not->toContain('href="'.e($url).'"', $path);
    }
})->with(['javascript' => 'javascript:alert(1)', 'spaced javascript' => ' JavaScript:alert(1)', 'data' => 'data:text/html,<script>alert(1)</script>', 'protocol-relative' => '//evil.example/x']);

it('escapes a user name in the admin users list', function () {
    User::factory()->create(['name' => XSS]);

    $html = $this->actingAs(userWithRole(RoleName::TechnicalAdmin))->get('/admin/users')->assertOk()->getContent();

    assertNoRawXss($html, 'admin users');
    expect($html)->toContain(e(XSS));
});
