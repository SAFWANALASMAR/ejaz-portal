<?php

namespace App\Support;

use App\Models\LibraryItem;

/**
 * صور رأس «مكتبة إعجاز» المتبدلة (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٥ — مراجعة المشرف على #157): صور المكتبة من
 * اللوحة أولاً (صورة رأس الصفحة من «صور رؤوس الصفحات»، ثم صورة شريحة المكتبة)، ثم أغلفة أحدث العناصر المنشورة، بلا تكرار وحتى أربع.
 * وصورة واحدة تُعرض ثابتة، ولا صورة يبقى للرأس رسمه المحايد (x-page-hero).
 */
final class LibraryHeroImages
{
    public const MAX = 4;

    /**
     * @return list<string>
     */
    public static function urls(): array
    {
        $panel = array_filter([
            HomeContent::pageHeroUrl('library.index'),
            HomeContent::heroSlideUrl('library'),
        ]);

        $covers = count(array_unique($panel)) >= self::MAX ? [] : LibraryItem::query()
            ->published()
            ->whereNotNull('thumbnail')
            ->where('thumbnail', '!=', '')
            ->newestFirst()
            ->limit(self::MAX)
            ->get()
            ->map(fn (LibraryItem $item): ?string => $item->thumbnailUrl())
            ->filter()
            ->all();

        return array_slice(array_values(array_unique([...$panel, ...$covers])), 0, self::MAX);
    }
}
