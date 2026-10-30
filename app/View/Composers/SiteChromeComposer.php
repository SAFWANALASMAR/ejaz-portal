<?php

namespace App\View\Composers;

use App\Models\LiveStream;
use App\Models\MenuItem;
use App\Models\Page;
use App\Models\Partner;
use App\Models\Setting;
use App\Support\ContentCache;
use App\Support\MenuLink;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

/**
 * يزوّد الرأس والتذييل بالقائمة الرئيسية وبيانات التواصل من الذاكرة المؤقتة.
 * تُخزَّن مصفوفات لا كائنات (cache.serializable_classes = false)، وتُمسح عند تعديل القائمة.
 */
class SiteChromeComposer
{
    public function compose(View $view): void
    {
        // الرأس والتذييل ونافذة البحث في كل صفحة: تُبنى بياناتها مرة واحدة في الطلب (خصائص الطلب نفسه)
        $attributes = request()->attributes;

        if (! $attributes->has(self::class)) {
            $attributes->set(self::class, [
                'siteMenu' => $this->menu(),
                'siteContact' => Setting::group('contact'),
                // شارة البث الجاري أو القادم في الرأس (docs/plans/livestream.md)
                'liveStream' => LiveStream::announced(),
                // شعارات الشركاء في التذييل (مصفوفات لا نماذج، cache.serializable_classes = false)، تُمسح مع المحتوى
                'footerPartners' => ContentCache::remember('footer.partners', fn (): array => Partner::query()->published()->whereNotNull('logo')
                    ->limit(4)->get(['name', 'logo'])->map(fn (Partner $partner): array => ['name' => $partner->name, 'logo' => (string) $partner->logo])->all()),
            ]);
        }

        $view->with((array) $attributes->get(self::class));
    }

    /**
     * @return Collection<int, MenuLink>
     */
    private function menu(): Collection
    {
        /** @var list<array{id: int, label: string, url: ?string, opens_in_new_tab: bool, children: list<array<string, mixed>>}> $rows */
        // ساعة احتياطية كما في Setting::group: التعديل يمسح الذاكرة فوراً
        $rows = Cache::remember(MenuItem::CACHE_KEY, now()->addHour(), fn (): array => MenuItem::query()
            ->whereNull('parent_id')
            ->where('is_visible', true)
            ->with(['children' => fn ($query) => $query->where('is_visible', true)])
            ->orderBy('sort')
            ->get()
            ->map(fn (MenuItem $item): array => [
                ...$item->only(['id', 'label', 'url', 'opens_in_new_tab']),
                'children' => $item->children->map(fn (MenuItem $child): array => $child->only(['id', 'label', 'url', 'opens_in_new_tab']))->all(),
            ])
            ->all());

        // ولا يظهر رابط إلى صفحة غير منشورة (Page::slugForUrl)، فلا يفتح الزائر صفحة 404 من القائمة. ويُفحص عند العرض
        // لا في المخزَّن، لأن نشر الصفحة يمسح ذاكرة المحتوى لا ذاكرة القائمة
        $published = Page::publishedSlugs();
        $linkable = fn (array $row): bool => ($slug = Page::slugForUrl((string) $row['url'])) === null || in_array($slug, $published, true);

        return collect($rows)
            ->map(fn (array $row): array => [
                ...$row,
                'linkable' => $linkable($row),
                'children' => array_values(array_filter($row['children'], $linkable)),
            ])
            // ويبقى العنصر الرئيسي إن بقيت له عناصر فرعية، زراً لقائمتها بلا رابط (MenuLink::$linkable)
            ->filter(fn (array $row): bool => $row['linkable'] || $row['children'] !== [])
            ->values()
            ->map(fn (array $row): MenuLink => MenuLink::fromArray($row));
    }
}
