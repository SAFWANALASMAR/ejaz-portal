<?php

namespace App\Support;

/**
 * صور رأس «مكتبة إعجاز» المتبدلة (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٥ — مراجعة المشرف على #157): صور المكتبة من
 * اللوحة (صورة رأس الصفحة من «صور رؤوس الصفحات»، ثم صورة شريحة المكتبة) بلا تكرار، والواحدة تُعرض ثابتة.
 *
 * وبلا صورة من اللوحة صور محايدة من المستودع بلا أشخاص: كتب على رف تحت قوس، ثم كتاب مفتوح (ملاحظات المدير ليلة 5 نوفمبر 2026،
 * البند 1). وما عادت أغلفة أحدث عناصر المكتبة تُستعمل: منها صور مقاطع فيها أشخاص (طفل يقرأ، وشيخ مغمض العينين). وصور المصحف
 * والرفوف من الجمعية ناقصة (docs/open-questions.md)، فتُرفع من اللوحة.
 */
final class LibraryHeroImages
{
    public const MAX = 4;

    /**
     * الصور المحايدة من المستودع (public/images/home)، بترتيب العرض.
     */
    public const DEFAULTS = ['images/home/value-3.jpg', 'images/home/value-1.jpg'];

    /**
     * @return list<string>
     */
    public static function urls(): array
    {
        $panel = array_values(array_unique(array_filter([
            HomeContent::pageHeroUrl('library.index'),
            HomeContent::heroSlideUrl('library'),
        ])));

        if ($panel !== []) {
            return array_slice($panel, 0, self::MAX);
        }

        return array_map(fn (string $path): string => asset($path), self::DEFAULTS);
    }
}
