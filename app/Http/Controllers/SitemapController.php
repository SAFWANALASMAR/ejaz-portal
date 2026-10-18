<?php

namespace App\Http\Controllers;

use App\Enums\LibrarySection;
use App\Models\Album;
use App\Models\Document;
use App\Models\Initiative;
use App\Models\LibraryItem;
use App\Models\NewsArticle;
use App\Models\Page;
use App\Support\ContentCache;
use DateTimeInterface;
use Illuminate\Http\Response;

/**
 * خريطة الموقع وملف robots لمحركات البحث (requirements/01 — ما قبل الإطلاق).
 */
class SitemapController extends Controller
{
    /**
     * الصفحات العامة الثابتة بأسماء مساراتها. ومسار كل وحدة عامة يُضاف هنا عند دمجها.
     */
    private const STATIC_ROUTES = [
        'home', 'initiatives.index', 'governance.index', 'library.index',
        'media.index', 'media.news.index', 'media.albums.index', 'media.partners', 'contact',
        'programs.index', 'competitions.index', 'services.index',
    ];

    public function sitemap(): Response
    {
        // نص في ذاكرة المحتوى، فيمسحه أي تعديل في المحتوى كبقية الصفحات
        $xml = ContentCache::remember('sitemap', fn (): string => '<?xml version="1.0" encoding="UTF-8"?>'."\n".view('sitemap', ['urls' => self::urls()])->render());

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /**
     * الفهرسة بمفتاح صريح (app.indexable) لا بالبيئة، لأن التجريبية مطابقة للإنتاج. وما يُمنع هنا
     * ما لا يحمل وسم noindex وحده؛ صفحات الحساب والدخول تحمله، ومنعها هنا يحجب الوسم عن الزاحف.
     */
    public function robots(): Response
    {
        $lines = config('app.indexable') === true
            ? ['User-agent: *', 'Disallow: /admin', 'Disallow: /'.trim((string) config('horizon.path'), '/'), 'Disallow: /livewire', '', 'Sitemap: '.route('sitemap')]
            : ['User-agent: *', 'Disallow: /'];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }

    /**
     * @return list<array{loc: string, lastmod: ?string}>
     */
    private static function urls(): array
    {
        $urls = array_map(fn (string $route): array => self::entry($route), self::STATIC_ROUTES);

        foreach (LibrarySection::cases() as $section) {
            $urls[] = self::entry('library.section', ['section' => $section->value]);
        }

        // «عن الجمعية» و«سياسة الخصوصية» برابطيهما الخاصين وحدهما، ومتى نُشرتا (PageController)
        // وصفحات المقدمة تظهر في روابط قوالبها، فلا تُذكر برابط يحوّل
        foreach (Page::query()->published()->whereNotIn('slug', array_keys(Page::INTROS))->orderBy('id')->get(['slug', 'updated_at']) as $page) {
            $urls[] = match ($page->slug) {
                Page::ABOUT => self::entry('about', [], $page->updated_at),
                Page::PRIVACY => self::entry('privacy', [], $page->updated_at),
                default => self::entry('pages.show', ['slug' => $page->slug], $page->updated_at),
            };
        }

        foreach (Initiative::query()->published()->get(['slug', 'updated_at']) as $initiative) {
            $urls[] = self::entry('initiatives.show', ['slug' => $initiative->slug], $initiative->updated_at);
        }

        foreach (Document::query()->published()->orderBy('id')->get(['slug', 'updated_at']) as $document) {
            $urls[] = self::entry('governance.show', ['slug' => $document->slug], $document->updated_at);
        }

        foreach (LibraryItem::query()->published()->orderBy('id')->get(['slug', 'section', 'updated_at']) as $item) {
            $urls[] = self::entry('library.show', ['section' => $item->section->value, 'slug' => $item->slug], $item->updated_at);
        }

        foreach (NewsArticle::query()->published()->orderBy('id')->get(['slug', 'updated_at']) as $article) {
            $urls[] = self::entry('media.news.show', ['slug' => $article->slug], $article->updated_at);
        }

        foreach (Album::query()->published()->orderBy('id')->get(['slug', 'updated_at']) as $album) {
            $urls[] = self::entry('media.albums.show', ['slug' => $album->slug], $album->updated_at);
        }

        return $urls;
    }

    /**
     * @param  array<string, string>  $parameters
     * @return array{loc: string, lastmod: ?string}
     */
    private static function entry(string $route, array $parameters = [], ?DateTimeInterface $lastmod = null): array
    {
        return ['loc' => route($route, $parameters), 'lastmod' => $lastmod?->format(DATE_ATOM)];
    }
}
