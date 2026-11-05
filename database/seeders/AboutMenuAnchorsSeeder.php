<?php

namespace Database\Seeders;

use App\Models\MenuItem;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * القائمة المنسدلة «عن الجمعية» في الرأس (إضافة توجيه المدير التنفيذي، 3 نوفمبر 2026، البند ٢٨ — docs/decisions/2026-11-03-director-home.md):
 * التوجه الاستراتيجي، ثم من نحن، ثم الرؤية، ثم الرسالة، ثم الأهداف، ثم القيم، ثم المبررات، ثم المبادرات، بترتيبها في الصفحة،
 * كل منها مرساة إلى مقطعه في صفحة «عن الجمعية». و«اللوائح والسياسات» تُخفى من هذه القائمة ولا تُحذف (المدير التنفيذي وصفوان،
 * 4 نوفمبر 2026 — docs/decisions/2026-11-04-about-menu-goals.md).
 *
 * يفرض في كل تشغيل ترتيب المراسي الثماني قبل العناصر القائمة (والصفحة الأم أول القائمة المنسدلة دائماً)، وإخفاء «اللوائح والسياسات»
 * بعنوانها ورابطها الافتراضيين: فإظهارها أو إعادة ترتيب المراسي من «القائمة» يعود كما هنا بعد تشغيله (مراجعة المشرف على #177، L2).
 * وما عدا ذلك لا يمسه: يضيف الناقص من المراسي، و«التوجه الاستراتيجي» الذي بقي بعنوانه ورابطه الافتراضيين (/about من MenuSeeder)
 * يصير مرساته. ولا يحذف شيئاً، ولا يغيّر عنوان عنصر أو رابطه، ولا ترتيب العناصر الأخرى، وتكراره لا يغيّر شيئاً:
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
        ['الأهداف', '/about#about-goals'],
        ['القيم', '/about#about-values'],
        ['المبررات', '/about#about-rationale'],
        ['المبادرات', '/about#about-initiatives'],
    ];

    /**
     * عناصر تُخفى من القائمة المنسدلة بعنوانها ورابطها الافتراضيين (من MenuSeeder)، ولا تُحذف.
     */
    public const HIDDEN = [
        ['اللوائح والسياسات', '/governance?category=policies'],
    ];

    public function run(): void
    {
        $parent = MenuItem::query()->whereNull('parent_id')->where('url', self::PARENT_URL)->orderBy('id')->first();

        if ($parent === null) {
            return;
        }

        DB::transaction(function () use ($parent): void {
            $children = MenuItem::query()->where('parent_id', $parent->id);
            // قبل كل العناصر الأخرى بترتيب متزايد لا يتساوى، ولا يتغير ترتيب العناصر الأخرى. والقائمة المنسدلة تضع الصفحة الأم أولاً
            // (MenuSections::children)، فتأتي المراسي بعدها مباشرة. والمراسي يُعاد ترتيبها كلها في كل تشغيل (ولو رتّبها المحرر)،
            // فما يُضاف لاحقاً (الأهداف) يأخذ موضعه. والعناصر بلا رابط من أساس الترتيب أيضاً: whereNotIn وحده يُسقط NULL (L3)
            $urls = array_column(self::ITEMS, 1);
            $first = (int) ((clone $children)->where(fn ($query) => $query->whereNull('url')->orWhereNotIn('url', $urls))->min('sort') ?? 0) - count(self::ITEMS);

            foreach (self::HIDDEN as [$label, $url]) {
                (clone $children)->where('label', $label)->where('url', $url)->update(['is_visible' => false]);
            }

            foreach (self::ITEMS as $index => [$label, $url]) {
                $sort = $first + $index;

                if (($existing = (clone $children)->where('url', $url)->first()) !== null) {
                    if ($existing->sort !== $sort) {
                        $existing->update(['sort' => $sort]);
                    }

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
