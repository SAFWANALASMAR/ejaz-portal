<?php

namespace Database\Seeders;

use App\Enums\SaudiRegion;
use App\Models\CompetitionCategory;
use App\Models\CompetitionRegion;
use Illuminate\Database\Seeder;

/**
 * قائمتا مسابقة الطلاب بقرار المدير التنفيذي (عبر صفوان، 25 أكتوبر 2026):
 * - الفئات: الثانوية، والجامعية، و«المهتمون بالثقافة العلمية» (اسم المطوية). وحُذفت المتوسطة بملاحظة المدير التنفيذي
 *   (29 أكتوبر 2026، وترحيل 2026_10_29_100000). وصفوف الثانوية صفوف التعليم العام المعروفة، والجامعية والمهتمون بلا صفوف
 *   حتى تحددها الجمعية.
 * - المناطق: المناطق الإدارية الثلاث عشرة، ومنطقة مكة المكرمة وحدها مفعّلة.
 * تُنشأ إن لم توجد بأسمائها، ثم تُدار من اللوحة، فلا يعيد البذر تعديلاً ولا تفعيلاً.
 */
class CompetitionListsSeeder extends Seeder
{
    /**
     * @var array<string, list<string>>
     */
    public const CATEGORIES = [
        'المرحلة الثانوية' => ['الأول الثانوي', 'الثاني الثانوي', 'الثالث الثانوي'],
        'المرحلة الجامعية' => [],
        'المهتمون بالثقافة العلمية' => [],
    ];

    public function run(): void
    {
        $sort = 0;

        foreach (self::CATEGORIES as $name => $grades) {
            CompetitionCategory::query()->firstOrCreate(['name' => $name], ['grades' => $grades === [] ? null : $grades, 'sort_order' => ++$sort]);
        }

        foreach (SaudiRegion::cases() as $index => $region) {
            CompetitionRegion::query()->firstOrCreate(['name' => $region->getLabel()], ['is_active' => $region === SaudiRegion::Makkah, 'sort_order' => $index + 1]);
        }
    }
}
