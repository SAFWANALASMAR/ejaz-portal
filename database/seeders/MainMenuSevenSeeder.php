<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * القائمة الرئيسية بسبعة عناصر (أمر التصور النهائي، النسخة 63 — البند R3): الرئيسية، عن الجمعية، مكتبة إعجاز، المركز الإعلامي،
 * الحوكمة، الخدمات الإلكترونية، تواصل معنا. لقائمة أُنشئت قبلها (MenuSeeder القديم): بيانات لا بنية، فلا ترحيل.
 *
 * - «الدورات والبرامج» (/programs) تصير «الخدمات الإلكترونية» (/services) بلا قائمة فرعية، فصفحتها تضم البرامج والمسابقات
 *   والمبادرات، وهي النشطة في صفحاتها (App\Support\MenuLink). وعناصرها الفرعية الثلاثة تُخفى ولا تُحذف.
 * - «المبادرات» و«المتجر» تُخفيان من الشريط (تبقيان في «القائمة» باللوحة لمن أراد إظهارهما).
 * - والترتيب بالسبعة، وما سواها بعدها بترتيبه.
 *
 * يمس ما بقي بعنوانه ورابطه الافتراضيين وحده، فلا يغيّر ما عدّله المحرر. ويُشغَّل مرة عند النشر، وتكراره لا يغيّر شيئاً:
 * php artisan db:seed --class=MainMenuSevenSeeder --force
 */
class MainMenuSevenSeeder extends Seeder
{
    /**
     * الروابط السبعة بترتيبها.
     */
    public const ORDER = ['/', '/about', '/library', '/media', '/governance', '/services', '/contact'];

    /**
     * عناصر تخرج من الشريط: العنوان والرابط الافتراضيان.
     */
    private const HIDDEN = [
        ['المبادرات', '/initiatives'],
        ['المتجر', '/store'],
    ];

    /**
     * العناصر الفرعية الافتراضية تحت «الدورات والبرامج».
     */
    private const PROGRAMS_CHILDREN = [
        ['الدورات والبرامج', '/programs'],
        ['المسابقات', '/competitions'],
        ['خدماتنا الإلكترونية', '/services'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $top = fn (string $label, string $url) => MenuItem::query()->whereNull('parent_id')->where('label', $label)->where('url', $url)->first();

            if (MenuItem::query()->whereNull('parent_id')->where('url', '/services')->doesntExist() && ($programs = $top('الدورات والبرامج', '/programs')) !== null) {
                foreach (self::PROGRAMS_CHILDREN as [$label, $url]) {
                    MenuItem::query()->where('parent_id', $programs->id)->where('label', $label)->where('url', $url)->update(['is_visible' => false]);
                }

                $programs->update(['label' => 'الخدمات الإلكترونية', 'url' => '/services']);
            }

            foreach (self::HIDDEN as [$label, $url]) {
                $top($label, $url)?->update(['is_visible' => false]);
            }

            $items = MenuItem::query()->whereNull('parent_id')->orderBy('sort')->orderBy('id')->get();
            $position = fn (MenuItem $item): int => ($index = array_search($item->url, self::ORDER, true)) === false ? count(self::ORDER) : $index;

            $items->sortBy(fn (MenuItem $item): array => [$position($item), $item->sort, $item->id])->values()
                ->each(fn (MenuItem $item, int $sort) => $item->sort === $sort ? null : $item->update(['sort' => $sort]));
        });
    }
}
