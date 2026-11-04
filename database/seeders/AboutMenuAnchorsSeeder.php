<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * القائمة المنسدلة «عن الجمعية» في الرأس (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٨ — docs/decisions/2026-11-03-director-home.md):
 * التوجه الاستراتيجي، ثم من نحن، ثم الرؤية، ثم الرسالة، ثم القيم، كل منها مرساة إلى مقطعه في صفحة «عن الجمعية».
 *
 * لا يمس ما عدّله المحرر في «القائمة»: يضيف الناقص وحده قبل العناصر القائمة (والصفحة الأم أول القائمة المنسدلة دائماً)، و«التوجه الاستراتيجي» الذي بقي بعنوانه ورابطه
 * الافتراضيين (/about من MenuSeeder) يصير مرساته معها. ولا يحذف شيئاً ولا يغيّر عنصراً غيّر المحرر عنوانه أو رابطه،
 * وتكراره لا يغيّر شيئاً:
 * php artisan db:seed --class=AboutMenuAnchorsSeeder --force
 */
class AboutMenuAnchorsSeeder extends Seeder
{
    public const PARENT_URL = '/about';

    /**
     * العناوين ومراسيها بالترتيب المعتمد.
     */
    public const ITEMS = [
        ['التوجه الاستراتيجي', '/about#about-strategy'],
        ['من نحن', '/about#about-who'],
        ['الرؤية', '/about#about-vision'],
        ['الرسالة', '/about#about-mission'],
        ['القيم', '/about#about-values'],
    ];

    public function run(): void
    {
        $parent = MenuItem::query()->whereNull('parent_id')->where('url', self::PARENT_URL)->orderBy('id')->first();

        if ($parent === null) {
            return;
        }

        DB::transaction(function () use ($parent): void {
            $children = MenuItem::query()->where('parent_id', $parent->id);
            // قبل كل العناصر القائمة بترتيب متزايد لا يتساوى، ولا يتغير ترتيب ما عدّله المحرر. والقائمة المنسدلة تضع الصفحة الأم أولاً
            // (MenuSections::children)، فتأتي المراسي بعدها مباشرة
            $first = (int) ((clone $children)->min('sort') ?? 0) - count(self::ITEMS);

            foreach (self::ITEMS as $index => [$label, $url]) {
                $sort = $first + $index;

                if ((clone $children)->where('url', $url)->exists()) {
                    continue;
                }

                // «التوجه الاستراتيجي» الافتراضي إلى الصفحة الأم نفسها: يصير مرساته، وما غيّره المحرر يبقى ويُضاف بجانبه
                $default = (clone $children)->where('label', $label)->where('url', self::PARENT_URL)->first();

                if ($default !== null) {
                    $default->update(['url' => $url, 'sort' => $sort]);

                    continue;
                }

                MenuItem::query()->create(['parent_id' => $parent->id, 'label' => $label, 'url' => $url, 'sort' => $sort, 'is_visible' => true]);
            }
        });
    }
}
