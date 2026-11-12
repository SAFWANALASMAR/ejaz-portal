<?php

use App\Enums\LibrarySection;
use App\Enums\OpeningKind;
use App\Models\Achievement;
use App\Models\Album;
use App\Models\Announcement;
use App\Models\Competition;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Opening;
use App\Models\Page;
use App\Models\Program;

it('lists the public pages and every published item with its last change', function () {
    Page::factory()->create(['slug' => 'vision']);
    Initiative::factory()->create(['slug' => 'qiyam']);
    Document::factory()->create(['slug' => 'bylaws']);
    LibraryItem::factory()->create(['slug' => 'first-book', 'section' => LibrarySection::Books]);
    LibraryItem::factory()->video()->create(['slug' => 'first-talk']);
    NewsArticle::factory()->create(['slug' => 'first-news']);
    Album::factory()->create(['slug' => 'first-album']);
    // و«إنجازاتنا» في الخريطة بأول إنجاز منشور (feat/seo-foundation)
    Achievement::factory()->published()->create();

    $response = $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

    $xml = simplexml_load_string((string) $response->getContent());
    expect($xml)->not->toBeFalse();

    $urls = collect(iterator_to_array($xml->url, false))->map(fn (SimpleXMLElement $url): string => (string) $url->loc)->all();

    expect($urls)->toContain(
        url('/'),
        url('/initiatives'),
        url('/governance'),
        url('/library'),
        url('/library/videos'),
        url('/library/audio'),
        url('/library/books'),
        url('/library/research'),
        url('/media'),
        url('/media/news'),
        url('/media/albums'),
        url('/media/partners'),
        url('/contact'),
        url('/achievements'),
        url('/pages/vision'),
        url('/initiatives/qiyam'),
        url('/governance/bylaws'),
        url('/library/books/first-book'),
        url('/library/videos/first-talk'),
        url('/media/news/first-news'),
        url('/media/albums/first-album'),
    );

    $news = collect(iterator_to_array($xml->url, false))->first(fn (SimpleXMLElement $url): bool => (string) $url->loc === url('/media/news/first-news'));
    expect($urls)->not->toContain(url('/about'));
    expect((string) $news->lastmod)->toBe(NewsArticle::query()->where('slug', 'first-news')->value('updated_at')->toAtomString());
});

it('leaves out unpublished items and private areas', function () {
    Page::factory()->unpublished()->create(['slug' => 'draft-page']);
    Initiative::factory()->create(['slug' => 'hidden', 'is_published' => false]);
    Document::factory()->create(['slug' => 'draft-doc', 'is_published' => false]);
    LibraryItem::factory()->unpublished()->create(['slug' => 'draft-book']);
    NewsArticle::factory()->create(['slug' => 'draft-news', 'is_published' => false]);
    Album::factory()->unpublished()->create(['slug' => 'draft-album']);

    $content = (string) $this->get('/sitemap.xml')->assertOk()->getContent();

    expect($content)
        ->not->toContain('draft-')
        ->not->toContain('/initiatives/hidden')
        ->not->toContain('/pages/about')
        ->not->toContain('/account')
        ->not->toContain('/admin')
        ->not->toContain('/login');
});

it('shows a newly published item without waiting for the cache to expire', function () {
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/media/news/late-news', false);

    NewsArticle::factory()->create(['slug' => 'late-news']);

    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/media/news/late-news'), false);
});

it('lists the about page under its own address once it is published', function () {
    Page::factory()->create(['slug' => Page::ABOUT]);

    $this->get('/sitemap.xml')->assertOk()
        ->assertSee('<loc>'.url('/about').'</loc>', false)
        ->assertDontSee('/pages/about', false);
});

it('lets search engines index the public site only when the copy is marked indexable, and points to the sitemap', function () {
    config(['app.indexable' => true]);

    $this->get('/robots.txt')->assertOk()
        ->assertHeader('Content-Type', 'text/plain; charset=UTF-8')
        ->assertSee('Disallow: /admin', false)
        ->assertSee('Disallow: /horizon', false)
        ->assertSee('Disallow: /livewire', false)
        ->assertSee('Sitemap: '.url('/sitemap.xml'), false)
        ->assertDontSee("Disallow: /\n", false);
});

it('keeps every page of a copy not marked indexable out of search engines', function () {
    $this->get('/robots.txt')->assertOk()
        ->assertSee("Disallow: /\n", false)
        ->assertDontSee('Sitemap:', false);
});

it('builds its links from the configured address, not from the request host', function () {
    config(['app.indexable' => true]);
    Page::factory()->create(['slug' => 'vision']);

    $this->withHeader('Host', 'evil.test')->get('/robots.txt')->assertOk()->assertDontSee('evil.test');
    $this->withHeader('Host', 'evil.test')->get('/sitemap.xml')->assertOk()
        ->assertSee(url('/pages/vision'), false)
        ->assertDontSee('evil.test');
});

/**
 * @return array<string, string> الرابط وآخر تعديل له
 */
function sitemapEntries(object $test): array
{
    $xml = simplexml_load_string((string) $test->get('/sitemap.xml')->assertOk()->getContent());

    return collect(iterator_to_array($xml->url, false))->mapWithKeys(fn (SimpleXMLElement $url): array => [(string) $url->loc => (string) $url->lastmod])->all();
}

it('lists the published programmes, competitions with their guide, and openings with their last change, and the services and announcements pages', function () {
    $program = Program::factory()->create(['slug' => 'open-program']);
    Program::factory()->unpublished()->create(['slug' => 'draft-program']);
    $competition = Competition::factory()->create(['slug' => 'open-competition']);
    $student = Competition::factory()->student()->create(['slug' => 'student-competition', 'page_content' => ['guide_steps' => ['خطوة']]]);
    Competition::factory()->student()->create(['slug' => 'no-guide', 'page_content' => ['vision' => 'رؤية']]);
    Competition::factory()->unpublished()->create(['slug' => 'draft-competition']);
    $opening = Opening::factory()->create(['slug' => 'open-job']);
    Opening::factory()->unpublished()->create(['slug' => 'draft-job']);
    // وقائمتا التطوع والإعلانات في الخريطة بأول عنصر فيهما (feat/seo-foundation)
    Opening::factory()->kind(OpeningKind::Volunteering)->create(['slug' => 'open-volunteering']);
    Announcement::factory()->create();

    $entries = sitemapEntries($this);

    expect($entries)->toHaveKeys([
        url('/services/jobs'),
        url('/services/volunteering'),
        url('/services/assembly-membership'),
        url('/media/announcements'),
        url('/programs/open-program'),
        url('/competitions/open-competition'),
        url('/competitions/student-competition'),
        url('/competitions/student-competition/guide'),
        url('/services/openings/open-job'),
    ])
        ->and($entries[url('/programs/open-program')])->toBe($program->updated_at->toAtomString())
        ->and($entries[url('/competitions/open-competition')])->toBe($competition->updated_at->toAtomString())
        ->and($entries[url('/competitions/student-competition/guide')])->toBe($student->updated_at->toAtomString())
        ->and($entries[url('/services/openings/open-job')])->toBe($opening->updated_at->toAtomString())
        ->and($entries)->toHaveKey(url('/competitions/no-guide'));

    foreach (['/programs/draft-program', '/competitions/draft-competition', '/competitions/no-guide/guide', '/services/openings/draft-job'] as $hidden) {
        expect($entries)->not->toHaveKey(url($hidden));
    }
});

it('shows a newly published opening in the sitemap at once', function () {
    $opening = Opening::factory()->unpublished()->create(['slug' => 'late-job']);
    $this->get('/sitemap.xml')->assertOk()->assertDontSee('/services/openings/late-job', false);

    $opening->update(['is_published' => true]);

    $this->get('/sitemap.xml')->assertOk()->assertSee(url('/services/openings/late-job'), false);
});
