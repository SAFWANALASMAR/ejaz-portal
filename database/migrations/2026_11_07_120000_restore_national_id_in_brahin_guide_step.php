<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الخطوة الثالثة في دليل دخول البراهين المخزّن بنص دليل الجمعية (PDF) حرفياً، برقم الهوية أو الإقامة لأن نموذج التسجيل
 * يطلبه (RegisterStudent، إلزامي ومشفّر)، بدل نص 2026_10_30_233000 الذي خلا منه (قرار صفوان والمدير التنفيذي، 7 نوفمبر
 * 2026 — docs/decisions/2026-11-07-brahin-guide-id-time.md). في مسابقات الطلاب وحدها، وما يطابق النص السابق حرفاً
 * وحده: النص الذي عدّله المسؤول من اللوحة لا يُمس. والرجوع يعيد السابق.
 */
return new class extends Migration
{
    private const PREVIOUS = 'الرجاء إدخال البيانات مع التأكد من صحتها: الاسم، والمرحلة التعليمية، والجهة التعليمية، ورقم الجوال، والبريد الإلكتروني.';

    private const NEW = 'أدخل البيانات المطلوبة بدقة، مثل الاسم ورقم الهوية أو الإقامة ورقم الجوال والبريد الإلكتروني والمرحلة التعليمية والجهة التعليمية، بحسب الحقول المعتمدة في المنصة.';

    public function up(): void
    {
        $this->replace(self::PREVIOUS, self::NEW);
    }

    public function down(): void
    {
        $this->replace(self::NEW, self::PREVIOUS);
    }

    private function replace(string $from, string $to): void
    {
        foreach (DB::table('competitions')->where('student_mode', true)->whereNotNull('page_content')->get(['id', 'page_content']) as $competition) {
            $content = json_decode((string) $competition->page_content, true);
            $steps = $content['guide_steps'] ?? null;

            if (! is_array($steps)) {
                continue;
            }

            $updated = array_map(fn ($step) => $step === $from ? $to : $step, $steps);

            if ($updated !== $steps) {
                $content['guide_steps'] = $updated;
                DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
