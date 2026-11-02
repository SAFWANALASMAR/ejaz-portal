<?php

namespace App\Support;

use App\Filament\Pages\HomeContentPage;
use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Filament\Resources\SitePages\SitePageResource;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\Setting;
use Illuminate\Routing\Route;

/**
 * سجل صفحات الموقع الثابتة في «محرر الصفحات» (قرار صفوان، 31 أكتوبر 2026 — docs/decisions/2026-11-02-page-editor.md): لكل صفحة
 * قالب مبني، وسجل صفحة (Page) بمعرّف ثابت يحمل عنوانها ووصفها ومقاطعها المنشورة، وصورة رأسها في خانتها القائمة
 * (home_sections.page_heroes — HomeContent::PAGE_HEROES)، فلا مصدر ثانٍ لما يُعرض. والمسودات والنسخ في page_revisions.
 *
 * والمفتاح هو مفتاح خانة صورة الرأس نفسه. و«عن الجمعية» سجلها القائم (about) بقالبها الذي يعرض مقاطعها. وصفحة «الإعلانات» خارجه،
 * لأن عنوانها ووصفها في «محتوى الرئيسية»، وكذلك الرئيسية نفسها.
 */
final class EditablePages
{
    /**
     * المفتاح ← [معرّف السجل، اسم المسار، مفتاح ترجمة العنوان الأصلي، مفتاح ترجمة الوصف الأصلي أو null].
     *
     * @var array<string, array{0: string, 1: string, 2: string, 3: ?string}>
     */
    public const PAGES = [
        'about' => [Page::ABOUT, 'about', 'site.footer.about', null],
        'initiatives' => ['site-initiatives', 'initiatives.index', 'content.initiatives.title', null],
        'programs' => ['site-programs', 'programs.index', 'programs.title', null],
        'competitions' => ['site-competitions', 'competitions.index', 'competitions.list.heading', null],
        'library' => ['site-library', 'library.index', 'library.title', null],
        'media' => ['site-media', 'media.index', 'media.title', null],
        'news' => ['site-news', 'media.news.index', 'news.title', null],
        'albums' => ['site-albums', 'media.albums.index', 'albums.title', null],
        'partners' => ['site-partners', 'media.partners', 'partners.title', null],
        'governance' => ['site-governance', 'governance.index', 'governance.title', null],
        'services' => ['site-services', 'services.index', 'services.title', 'services.intro'],
        'contact' => ['site-contact', 'contact', 'contact.title', null],
        'assembly' => ['site-assembly', 'assembly.index', 'assembly.title', null],
    ];

    /**
     * حدود المحتوى، وهي حدود محرر الصفحات القائم (ContentPageForm وContentFields).
     */
    public const TITLE_MAX = 160;

    public const SUMMARY_MAX = 300;

    public const BLOCKS_MAX = 30;

    public static function slug(string $key): string
    {
        return self::PAGES[$key][0];
    }

    public static function keyForSlug(?string $slug): ?string
    {
        foreach (self::PAGES as $key => [$pageSlug]) {
            if ($pageSlug === $slug) {
                return $key;
            }
        }

        return null;
    }

    public static function keyForRoute(?string $routeName): ?string
    {
        foreach (self::PAGES as $key => [, $route]) {
            if ($route === $routeName) {
                return $key;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    public static function slugs(): array
    {
        return array_values(array_map(fn (array $page): string => $page[0], self::PAGES));
    }

    /**
     * سجل الصفحة الثابتة يُعرض في قالبه لا برابط /pages/{slug}، فيُحوَّل إليه (PageController). و«عن الجمعية» برابطها الخاص.
     */
    public static function templateUrl(string $slug, bool $absolute = true): ?string
    {
        $key = self::keyForSlug($slug);

        return $key === null || $key === 'about' ? null : route(self::PAGES[$key][1], [], $absolute);
    }

    public static function isEditable(Page $page): bool
    {
        return self::keyForSlug($page->slug) !== null;
    }

    /**
     * المحتوى الأصلي لسجل صفحة جديد: نصوص قالبها كما تُعرض الآن، بلا مقاطع.
     *
     * @return array{title: string, summary: ?string}
     */
    public static function defaults(string $key): array
    {
        [, , $title, $summary] = self::PAGES[$key];

        return ['title' => (string) __($title), 'summary' => $summary === null ? null : (string) __($summary)];
    }

    /**
     * صورة رأس الصفحة المحفوظة في خانتها (مسار على القرص العام)، أو null.
     */
    public static function heroImage(string $key): ?string
    {
        $path = Setting::group(HomeContent::KEY)['page_heroes'][$key] ?? null;

        return is_string($path) && trim($path) !== '' ? trim($path) : null;
    }

    /**
     * ما يُعرض الآن من الصفحة: عنوان سجلها ووصفه ومقاطعه وصورة خانتها، بشكل محتوى النسخة.
     *
     * @return array{title: string, summary: ?string, image: ?string, blocks: list<array{type: string, data: array<string, mixed>}>}
     */
    public static function liveContent(Page $page): array
    {
        $key = (string) self::keyForSlug($page->slug);

        return self::normalize([
            'title' => $page->title,
            'summary' => $page->summary,
            'image' => self::heroImage($key),
            'blocks' => $page->blocks,
        ]);
    }

    /**
     * محتوى نسخة بشكل واحد مهما وصل: المقاطع المعروفة بترتيبها، ومفتاح الإظهار منطقياً.
     *
     * @param  array<string, mixed>  $content
     * @return array{title: string, summary: ?string, image: ?string, blocks: list<array{type: string, data: array<string, mixed>}>}
     */
    public static function normalize(array $content): array
    {
        $text = fn (mixed $value): ?string => is_string($value) && trim($value) !== '' ? trim($value) : null;

        $blocks = array_map(function (array $block): array {
            // قاعدة boolean تقبل 0 و"0" و"false" أيضاً، فتُحوَّل كلها إلى منطقي قبل الحفظ (المراجعة الأمنية، ن٢)
            $block['data']['visible'] = filter_var($block['data']['visible'] ?? true, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

            return $block;
        }, ContentBlocks::normalize($content['blocks'] ?? []));

        return [
            'title' => (string) $text($content['title'] ?? null),
            'summary' => $text($content['summary'] ?? null),
            'image' => $text($content['image'] ?? null),
            'blocks' => $blocks,
        ];
    }

    /**
     * كل صورة يعرفها المحرر لهذه الصفحة: في سجلها، وفي خانة رأسها، وفي أي نسخة منها. منع التلاعب بالمسار في حقول المحرر يقبلها
     * وحدها مع ما يُرفع الآن، فلا تُلصق صورة سجل آخر.
     *
     * @return list<string>
     */
    public static function knownImages(Page $page): array
    {
        $key = self::keyForSlug($page->slug);
        $paths = [...ContentBlocks::images($page->blocks), ...array_filter([$key === null ? null : self::heroImage($key)])];

        foreach (PageRevision::query()->where('page_id', $page->id)->get(['id', 'page_id', 'content']) as $revision) {
            array_push($paths, ...array_merge(...array_values($revision->publicImages())));
        }

        return array_values(array_unique($paths));
    }

    /**
     * صور يملكها غير هذه الصفحة في مجلدي المحرر: مقاطع الصفحات الأخرى ونسخها، وصور «محتوى الرئيسية» وخانات الصفحات الأخرى. فلا
     * تُربط بالصفحة صورة سجل آخر فيحذفها حفظه لاحقاً أو يعيد ترميزها، ولو من غير الشاشة (المراجعة الأمنية، ن٣).
     *
     * @return list<string>
     */
    public static function foreignImages(Page $page): array
    {
        $paths = HomeContent::imagePaths(Setting::group(HomeContent::KEY));

        foreach (Page::query()->whereKeyNot($page->id)->get(['id', 'blocks']) as $other) {
            array_push($paths, ...ContentBlocks::images($other->blocks));
        }

        foreach (PageRevision::query()->where('page_id', '!=', $page->id)->get(['id', 'page_id', 'content']) as $revision) {
            array_push($paths, ...array_merge(...array_values($revision->publicImages())));
        }

        return array_values(array_diff(array_unique($paths), self::knownImages($page)));
    }

    /**
     * صور رؤوس صفحات المحرر المحفوظة وصور كل نسخها: لا يحذفها حفظ «محتوى الرئيسية» ولا يقبلها في خاناته (المراجعة الأمنية، م١).
     *
     * @param  array<string, mixed>  $stored  مجموعة home_sections
     * @return list<string>
     */
    public static function ownedImages(array $stored): array
    {
        $paths = [];

        foreach (array_keys(self::PAGES) as $key) {
            $path = $stored['page_heroes'][$key] ?? null;

            if (is_string($path) && $path !== '') {
                $paths[] = $path;
            }
        }

        foreach (PageRevision::query()->get(['id', 'page_id', 'content']) as $revision) {
            array_push($paths, ...array_merge(...array_values($revision->publicImages())));
        }

        return array_values(array_unique($paths));
    }

    /**
     * عنوان الصفحة الثابتة المنشورة ووصفها ومقاطعها الظاهرة (نصاً) لاسم المسار، أو [] لما ليس منها أو لم يُنشر. في ذاكرة المحتوى،
     * فيمسحها حفظ أي صفحة. و«عن الجمعية» يعرضها قالبها.
     *
     * @return array{title?: string, summary?: ?string, html?: string}
     */
    public static function live(?string $routeName): array
    {
        $key = self::keyForRoute($routeName);

        if ($key === null || $key === 'about') {
            return [];
        }

        return ContentCache::remember('editable-pages.'.$key, function () use ($key): array {
            $page = Page::query()->published()->where('slug', self::slug($key))->first();

            return $page === null ? [] : [
                'title' => $page->title,
                'summary' => $page->summary,
                'html' => view('components.content-blocks', ['blocks' => $page->blocks])->render(),
            ];
        });
    }

    /**
     * رابط شاشة الصفحة في اللوحة لزر «تعديل هذه الصفحة»، أو null لصفحة لا شاشة لها. والزر لمن له pages.edit_all وحده
     * (partials/page-edit-button)، فلا يُفحص هنا.
     */
    public static function adminUrl(?Route $route): ?string
    {
        $name = $route?->getName();

        if ($name === 'home') {
            return HomeContentPage::getUrl(panel: 'admin');
        }

        if (($key = self::keyForRoute($name)) !== null) {
            return SitePageResource::getUrl('open', ['key' => $key], panel: 'admin');
        }

        $slug = match ($name) {
            'privacy' => Page::PRIVACY,
            'pages.show' => $route->parameter('slug'),
            default => null,
        };

        $page = is_string($slug) ? Page::query()->where('slug', $slug)->first(['id', 'slug']) : null;

        return $page === null || self::isEditable($page) ? null : ContentPageResource::getUrl('edit', ['record' => $page], panel: 'admin');
    }
}
