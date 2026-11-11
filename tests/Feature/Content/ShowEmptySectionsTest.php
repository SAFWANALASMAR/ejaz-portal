<?php

use App\Enums\DocumentCategory;
use App\Enums\LibrarySection;
use App\Models\Page;
use App\Models\Setting;
use App\Support\HomeContent;
use App\Support\MenuSections;

/*
| قرار صفوان (11 نوفمبر 2026، 17:26): «لا تخفي ولا شيء، خلي كل شيء بالموقع ظاهر حتى لو ما فيه محتوى»
| (docs/decisions/2026-11-11-fix-show-empty-sections.md). كل مقطع بقاعدة بيانات فارغة يظهر بحالته الفارغة، وإعدادات الإظهار
| اليدوية باقية تعمل.
*/

beforeEach(fn () => config(['app.hide_missing_markers' => true]));

function homeSection(string $html, string $marker): string
{
    return (string) str($html)->after($marker)->before('</section>');
}

it('shows every home section on an empty database, each with its empty state', function () {
    $html = (string) $this->get('/')->assertOk()->getContent();

    expect(substr_count(homeSection($html, 'id="home-identity"'), 'data-identity-card="'))->toBeGreaterThanOrEqual(5);

    foreach (['videos', 'books', 'x', 'announcements', 'news', 'partners'] as $section) {
        expect($html)->toContain('data-home-empty="'.$section.'"');
    }

    expect($html)->toContain(__('home.empty.announcements'))
        ->and($html)->toContain(__('home.empty.news'))
        ->and($html)->toContain(__('home.empty.partners'))
        ->and($html)->not->toContain('【')
        // العمودان متجاوران على الحاسوب ولو فرغا
        ->and(homeSection($html, 'id="home-announcements"'))->not->toBe('')
        ->and($html)->toMatch('/lg:grid-cols-2[^>]*>\s*<div data-feed-column/');
});

it('keeps the manual show settings working: a section switched off stays hidden', function () {
    Setting::put('home', ['show_partners' => false, 'show_latest' => false]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->not->toContain('id="home-partners"')
        ->and($html)->not->toContain('id="home-news"')
        // والإعلانات وحدها بعرض كامل
        ->and($html)->toContain('data-home-empty="announcements"')
        ->and($html)->not->toMatch('/lg:grid-cols-2[^>]*>\s*<div data-feed-column/');
});

it('shows the portal numbers once switched on, with an empty state before the first count', function () {
    $this->get('/')->assertOk()->assertDontSee('data-portal-stats', false);

    Setting::put(HomeContent::KEY, ['portal_stats' => ['enabled' => true]]);

    $this->get('/')->assertOk()
        ->assertSee('data-portal-stats', false)
        ->assertSee('data-home-empty="portal_stats"', false)
        ->assertSee(__('home.empty.portal_stats'));
});

it('shows the optional older sections switched on from the settings with their empty states', function () {
    Setting::put('home', ['show_initiatives' => true, 'show_videos' => true, 'show_video' => true, 'video_url' => null]);

    $html = (string) $this->get('/')->assertOk()->getContent();

    expect($html)->toContain('data-home-empty="initiatives"')
        ->and($html)->toContain('data-home-empty="old-videos"')
        ->and($html)->toContain('data-home-empty="video"');
});

it('keeps the empty sections of the inner pages', function () {
    $media = (string) $this->get('/media')->assertOk()->getContent();
    expect($media)->toContain('id="media-albums"')
        ->and($media)->toContain(__('albums.list.empty'))
        ->and($media)->toContain('data-survey-tile');

    $library = (string) $this->get('/library')->assertOk()->getContent();
    foreach (LibrarySection::cases() as $section) {
        expect($library)->toContain('data-library-empty="'.$section->value.'"');
    }

    Page::factory()->create(['slug' => Page::ABOUT, 'is_published' => true, 'blocks' => []]);
    $about = (string) $this->get(route('about'))->assertOk()->getContent();
    expect($about)->toContain('data-about-empty="board"')
        ->and($about)->toContain('data-about-empty="initiatives"')
        ->and($about)->toContain('data-about-empty="strategy"')
        ->and($about)->not->toContain('【');
});

it('lists every governance category and the satisfaction survey in the header dropdowns, documents or not', function () {
    expect(array_column(MenuSections::for('/governance'), 'url'))
        ->toBe(array_map(fn (DocumentCategory $category): string => '/governance?category='.$category->value, DocumentCategory::cases()))
        ->and(array_column(MenuSections::for('/media'), 'url'))->toContain('/surveys');
});
