<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Support\ContentCache;
use Illuminate\Database\Seeder;
use RuntimeException;

/**
 * مقطع «أهداف الجمعية» تجريبي في صفحة «عن الجمعية» إن غاب، لاختبار مراسيها في المتصفح (tests/browser/about-anchors.spec.mjs —
 * المشرف على طلب الدمج 183: لا يُتخطى الاختبار صامتاً). في البيئة المحلية وحدها، ونصه علامة «ناقص» لا محتوى مخترع، ولا ينشر صفحة
 * غير منشورة ولا يمس مقطعاً قائماً. وتكراره لا يغيّر شيئاً:
 *
 *   php artisan db:seed --class=DemoAboutGoalsSeeder
 */
class DemoAboutGoalsSeeder extends Seeder
{
    public const HEADING = 'أهداف الجمعية';

    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new RuntimeException('DemoAboutGoalsSeeder: بيانات تجريبية للبيئة المحلية وحدها.');
        }

        $page = Page::query()->where('slug', Page::ABOUT)->first();

        if ($page === null) {
            return;
        }

        /** @var list<array{type?: string, data?: array<string, mixed>}> $blocks */
        $blocks = (array) $page->blocks;

        foreach ($blocks as $block) {
            if (($block['data']['heading'] ?? null) === self::HEADING) {
                return;
            }
        }

        $blocks[] = ['type' => 'text', 'data' => ['heading' => self::HEADING, 'body' => '<p>'.e(__('content.about.demo_goals')).'</p>']];
        // بلا أحداث الحفظ: الصفحة منشورة، وقاعدة النشر (Publishable) ترفض نصاً فيه علامة «ناقص»، فكان الأمر يفشل على رابط العرض
        // نفسه. والتجاوز مقصود هنا وحده، للمحلية والاختبار، ثم يُمسح مخزن المحتوى كما يمسحه الحفظ (اختبار المشرف على 183، ٢)
        Page::withoutEvents(fn () => $page->forceFill(['blocks' => $blocks])->save());
        ContentCache::flush();
    }
}
