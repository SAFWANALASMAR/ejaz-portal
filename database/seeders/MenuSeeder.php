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
            ['label' => 'الدورات والبرامج', 'url' => '/programs', 'children' => [
                ['label' => 'الدورات والبرامج', 'url' => '/programs'],
                ['label' => 'المسابقات', 'url' => '/competitions'],
                ['label' => 'خدماتنا الإلكترونية', 'url' => '/services'],
            ]],
            ['label' => 'المبادرات', 'url' => '/initiatives'],
            // المركز الإعلامي: الأخبار والإعلانات وحدهما، و«مكتبة إعجاز» بفروعها الأربعة عنصر مستقل (ملاحظات المدير التنفيذي،
            // 25 أكتوبر 2026). ومكتبة الصور والشركاء باقيتان بروابطهما، وتُضافان من «القائمة» متى أُريد
            ['label' => 'المركز الإعلامي', 'url' => '/media', 'children' => [
                ['label' => 'الأخبار', 'url' => '/media/news'],
                ['label' => 'الإعلانات', 'url' => '/media/announcements'],
            ]],
            ['label' => 'مكتبة إعجاز', 'url' => '/library', 'children' => [
                ['label' => 'المكتبة المرئية', 'url' => '/library/videos'],
                ['label' => 'المكتبة الصوتية', 'url' => '/library/audio'],
                ['label' => 'الكتب', 'url' => '/library/books'],
                ['label' => 'الأبحاث والدراسات', 'url' => '/library/research'],
            ]],
            ['label' => 'الحوكمة', 'url' => '/governance'],
            // المتجر المعتمد على سلة (قرار صفوان): /store يحوّل إليه، ويفتح في تبويب جديد
            ['label' => 'المتجر', 'url' => '/store', 'new_tab' => true],
            ['label' => 'تواصل معنا', 'url' => '/contact'],
        ];

        foreach ($menu as $sort => $item) {
            $parent = MenuItem::create(['label' => $item['label'], 'url' => $item['url'], 'sort' => $sort, 'opens_in_new_tab' => $item['new_tab'] ?? false]);

            foreach ($item['children'] ?? [] as $childSort => $child) {
                MenuItem::create(['parent_id' => $parent->id, 'label' => $child['label'], 'url' => $child['url'], 'sort' => $childSort,
                    'is_visible' => ! str_starts_with($child['url'], '/governance/')]);
            }
        }
    }
}
