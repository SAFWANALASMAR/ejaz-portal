<?php

namespace App\Support;

/**
 * صور محايدة بلا أشخاص (ملاحظة صفوان، 1 نوفمبر 2026): تدرج أزرق ونقش هندسي، لكل صفحة داخلية نقشها في
 * public/images/placeholders/pages، فلا تتكرر صورة واحدة في رؤوس الصفحات ولا في بطاقات بلا صورة. وتبقى حتى تُرفع صورة
 * الصفحة من «محتوى الرئيسية» ← «صور رؤوس الصفحات» أو صورة السجل نفسه.
 */
final class NeutralImage
{
    public const DIRECTORY = 'images/placeholders/pages';

    /**
     * صورة رأس الصفحة لاسم مسارها: خانتها في HomeContent::PAGE_HEROES، وإلا قسمها بأطول بادئة (media.news.show ← الأخبار)،
     * وإلا العامة.
     */
    public static function forPage(?string $routeName): string
    {
        $key = 'default';
        $longest = 0;

        foreach (HomeContent::PAGE_HEROES as $page => $route) {
            $prefix = str_contains($route, '.') ? substr($route, 0, (int) strrpos($route, '.') + 1) : $route.'.';

            if ($routeName === $route) {
                $key = $page;
                break;
            }

            if ($routeName !== null && str_starts_with($routeName, $prefix) && strlen($prefix) > $longest) {
                [$key, $longest] = [$page, strlen($prefix)];
            }
        }

        return asset(self::DIRECTORY.'/'.$key.'.svg');
    }

    /**
     * صورة بطاقة بلا صورة (برنامج، مبادرة): تتعاقب على النقوش برقم السجل، فلا تتكرر صورة واحدة في الشبكة.
     */
    public static function forSeed(int $seed): string
    {
        // بلا «إنجازاتنا»: نقشها العام نفسه، فلا يتكرر في الشبكة ولا يتغير تعاقب البطاقات القائم
        $keys = array_values(array_diff(array_keys(HomeContent::PAGE_HEROES), ['achievements']));

        return asset(self::DIRECTORY.'/'.$keys[abs($seed) % count($keys)].'.svg');
    }
}
