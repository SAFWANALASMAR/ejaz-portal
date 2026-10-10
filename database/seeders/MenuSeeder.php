<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * القائمة الرئيسية الافتراضية حسب المرجع البصري (docs/design/ui-reference.md — الرأس).
 * تُنشأ مرة واحدة إن كانت القائمة فارغة، ثم تُدار من لوحة الإدارة.
 * المكتبة والخدمات الإلكترونية والمسابقات عناصر فرعية حتى تبقى في متناول الزائر.
 */
class MenuSeeder extends Seeder
{
    public function run(): void
    {
        if (MenuItem::query()->exists()) {
            return;
        }

        $menu = [
            ['label' => 'الرئيسية', 'url' => '/'],
            ['label' => 'عن الجمعية', 'url' => '/about'],
            ['label' => 'الدورات والبرامج', 'url' => '/programs', 'children' => [
                ['label' => 'الدورات والبرامج', 'url' => '/programs'],
                ['label' => 'المسابقات', 'url' => '/competitions'],
                ['label' => 'خدماتنا الإلكترونية', 'url' => '/services'],
            ]],
            ['label' => 'المبادرات', 'url' => '/initiatives'],
            ['label' => 'المركز الإعلامي', 'url' => '/media', 'children' => [
                ['label' => 'الأخبار', 'url' => '/news'],
                ['label' => 'مكتبة إعجاز', 'url' => '/library'],
                ['label' => 'مكتبة الصور', 'url' => '/media/photos'],
                ['label' => 'مكتبة الفيديو', 'url' => '/media/videos'],
                ['label' => 'الشركاء', 'url' => '/partners'],
            ]],
            ['label' => 'الحوكمة', 'url' => '/governance'],
            // المتجر المعتمد على سلة (قرار صفوان): /store يحوّل إليه، ويفتح في تبويب جديد
            ['label' => 'المتجر', 'url' => '/store', 'new_tab' => true],
            ['label' => 'تواصل معنا', 'url' => '/contact'],
        ];

        foreach ($menu as $sort => $item) {
            $parent = MenuItem::create(['label' => $item['label'], 'url' => $item['url'], 'sort' => $sort, 'opens_in_new_tab' => $item['new_tab'] ?? false]);

            foreach ($item['children'] ?? [] as $childSort => $child) {
                MenuItem::create(['parent_id' => $parent->id, 'label' => $child['label'], 'url' => $child['url'], 'sort' => $childSort]);
            }
        }
    }
}
