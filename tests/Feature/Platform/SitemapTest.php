<?php

use App\Enums\LibrarySection;
use App\Models\Album;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Page;

it('lists the public pages and every published item with its last change', function () {
    Page::factory()->create(['slug' => 'vision']);
    Initiative::factory()->create(['slug' => 'qiyam']);
    Document::factory()->create(['slug' => 'bylaws']);
    LibraryItem::factory()->create(['slug' => 'first-book', 'section' => LibrarySection::Books]);
    LibraryItem::factory()->video()->create(['slug' => 'first-talk']);
    NewsArticle::factory()->create(['slug' => 'first-news']);
    Album::factory()->create(['slug' => 'first-album']);

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
