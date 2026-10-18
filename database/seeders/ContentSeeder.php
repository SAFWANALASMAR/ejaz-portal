<?php

namespace Database\Seeders;

use App\Models\Initiative;
use App\Models\Page;
use Illuminate\Database\Seeder;

/**
 * صفحة «عن الجمعية» والمبادرات الخمس بأسمائها الرسمية (docs/overview.md)، غير منشورة
 * وبلا نصوص. تُنقل النصوص المعتمدة في بند النقل (docs/content-inventory.md)، فلا يُخترع نص.
 * و«سياسة الخصوصية» مسودة بعلامة النقص حتى يصل نصها المعتمد (docs/open-questions.md — مانع للإطلاق).
 * تُنشأ مرة واحدة، ثم تُدار من لوحة الإدارة.
 */
class ContentSeeder extends Seeder
{
    public function run(): void
    {
        Page::query()->firstOrCreate(['slug' => Page::ABOUT], ['title' => 'عن الجمعية', 'is_published' => false]);

        Page::query()->firstOrCreate(['slug' => Page::PRIVACY], [
            'title' => 'سياسة الخصوصية',
            'blocks' => [['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>【ناقص: نص سياسة الخصوصية المعتمد من الجمعية】</p>']]],
            'is_published' => false,
        ]);

        // المبادرات مرة واحدة كالقائمة، فلا تعود مبادرة حذفها المحرر بعد كل بذر
        if (Initiative::query()->exists()) {
            return;
        }

        $initiatives = ['qiyam' => 'قيم', 'tatweer' => 'تطوير', 'kunooz' => 'كنوز', 'ibtikar' => 'ابتكار', 'irtiqa' => 'ارتقاء'];

        foreach (array_keys($initiatives) as $sort => $slug) {
            Initiative::query()->firstOrCreate(['slug' => $slug], [
                'name' => $initiatives[$slug],
                'sort' => $sort + 1,
                'is_published' => false,
            ]);
        }
    }
}
