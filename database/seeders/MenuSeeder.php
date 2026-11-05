<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;

/**
 * القائمة الرئيسية الافتراضية: سبعة عناصر بترتيب أمر التصور النهائي، النسخة 63 (البند R3، وdocs/design/ui-reference.md — الرأس):
 * الرئيسية، عن الجمعية، مكتبة إعجاز، المركز الإعلامي، الحوكمة، الخدمات الإلكترونية، تواصل معنا.
 * تُنشأ مرة واحدة إن كانت القائمة فارغة، ثم تُدار من لوحة الإدارة. والقائمة القائمة تُعاد إلى الشكل نفسه بـ MainMenuSevenSeeder.
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
            // «عن الجمعية» بقائمتها في الموقع السابق (أرشيف الموقع — القائمة الرئيسية). وعناصر الوثائق
            // مخفية حتى تُنشر الوثائق المستوردة مسودات
            ['label' => 'عن الجمعية', 'url' => '/about', 'children' => [
                ['label' => 'عن الجمعية', 'url' => '/about'],
                ['label' => 'شهادة ترخيص الجمعية', 'url' => '/governance/license-certificate'],
                ['label' => 'التوجه الاستراتيجي', 'url' => '/about'],
                ['label' => 'قرار تسجيل الجمعية', 'url' => '/governance/registration-decision'],
                ['label' => 'قرار تشكيل مجلس الإدارة', 'url' => '/governance/board-formation-decision'],
                ['label' => 'الفروع', 'url' => '/governance/no-branches-statement'],
                ['label' => 'اللوائح والسياسات', 'url' => '/governance?category=policies'],
                ['label' => 'آلية التحقق', 'url' => '/governance/beneficiary-eligibility-mechanism'],
                ['label' => 'آلية استرداد', 'url' => '/governance/donation-refund-mechanism'],
                ['label' => 'وثيقة الافصاح', 'url' => '/governance/disclosure-document'],
            ]],
            // مكتبة إعجاز بعد «عن الجمعية» بأقسامها الأربعة (أمر التصور النهائي، النسخة 63 — البند R3)
            ['label' => 'مكتبة إعجاز', 'url' => '/library', 'children' => [
                ['label' => 'المكتبة المرئية', 'url' => '/library/videos'],
                ['label' => 'المكتبة الصوتية', 'url' => '/library/audio'],
                ['label' => 'الكتب والمؤلفات', 'url' => '/library/books'],
                ['label' => 'الأبحاث والدراسات', 'url' => '/library/research'],
            ]],
            // المركز الإعلامي: الأخبار والإعلانات وحدهما (ملاحظات المدير التنفيذي، 25 أكتوبر 2026). ومكتبة الصور والشركاء باقيتان
            // بروابطهما، وتُضافان من «القائمة» متى أُريد
            ['label' => 'المركز الإعلامي', 'url' => '/media', 'children' => [
                ['label' => 'الأخبار', 'url' => '/media/news'],
                ['label' => 'الإعلانات', 'url' => '/media/announcements'],
            ]],
            ['label' => 'الحوكمة', 'url' => '/governance'],
            // «الخدمات الإلكترونية» تضم الدورات والبرامج والمسابقات والمبادرات في صفحتها، وهي النشطة في صفحاتها (MenuLink)
            ['label' => 'الخدمات الإلكترونية', 'url' => '/services'],
            ['label' => 'تواصل معنا', 'url' => '/contact'],
            // صفحة «المتجر والتبرعات» في الموقع (docs/decisions/2026-11-04-store-page.md)، في التبويب نفسه (قرار المشرف على #179،
            // البند 2). مخفي من الشريط (سبعة عناصر فقط — R3)، ويُظهر من «القائمة» متى أُريد
            ['label' => 'المتجر', 'url' => '/store', 'hidden' => true],
        ];

        foreach ($menu as $sort => $item) {
            $parent = MenuItem::create(['label' => $item['label'], 'url' => $item['url'], 'sort' => $sort, 'opens_in_new_tab' => false,
                'is_visible' => ! ($item['hidden'] ?? false)]);

            foreach ($item['children'] ?? [] as $childSort => $child) {
                MenuItem::create(['parent_id' => $parent->id, 'label' => $child['label'], 'url' => $child['url'], 'sort' => $childSort,
                    'is_visible' => ! str_starts_with($child['url'], '/governance/')]);
            }
        }
    }
}
