<?php

namespace App\View\Composers;

use App\Models\MenuItem;
use App\Models\Setting;
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
        $view->with('siteMenu', $this->menu());
        $view->with('siteContact', Setting::group('contact'));
    }

    /**
     * @return Collection<int, MenuLink>
     */
    private function menu(): Collection
    {
        /** @var list<array{id: int, label: string, url: ?string, opens_in_new_tab: bool, children: list<array<string, mixed>>}> $rows */
        $rows = Cache::rememberForever(MenuItem::CACHE_KEY, fn (): array => MenuItem::query()
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

        return collect($rows)->map(fn (array $row): MenuLink => MenuLink::fromArray($row));
    }
}
