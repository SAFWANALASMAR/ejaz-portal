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
        return asset(self::DIRECTORY.'/'.(HomeContent::pageKey($routeName) ?? 'default').'.svg');
    }

    /**
     * صورة بطاقة بلا صورة (برنامج، مبادرة): تتعاقب على النقوش برقم السجل، فلا تتكرر صورة واحدة في الشبكة.
     */
    public static function forSeed(int $seed): string
    {
        // بلا «إنجازاتنا» و«المتجر»: أُضيفتا بعد التعاقب، فلا يتغير تعاقب البطاقات القائم
        $keys = array_values(array_diff(array_keys(HomeContent::PAGE_HEROES), ['achievements', 'store']));

        return asset(self::DIRECTORY.'/'.$keys[abs($seed) % count($keys)].'.svg');
    }
}
