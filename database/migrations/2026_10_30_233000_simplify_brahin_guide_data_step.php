<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الخطوة الثالثة في دليل دخول البراهين المخزّن بنص صفوان حرفياً (30 أكتوبر 2026، 23:38، في NEW) بدل كل الصيغ السابقة التي تبدأ بـ «أدخل البيانات المطلوبة بدقة»، بلا رقم
 * الهوية لأن المنصة لا تطلبه. في مسابقات الطلاب وحدها، والبذرة صُحّحت للتثبيت الجديد، ومطوية الجمعية (PDF) لا تُمس.
 */
return new class extends Migration
{
    private const PREFIX = 'أدخل البيانات المطلوبة بدقة';

    private const NEW = 'الرجاء إدخال البيانات مع التأكد من صحتها: الاسم، والمرحلة التعليمية، والجهة التعليمية، ورقم الجوال، والبريد الإلكتروني.';

    /** نص الخطوة بعد ترحيل 2026_10_30_223000، يعيده الرجوع */
    private const PREVIOUS = 'أدخل البيانات المطلوبة بدقة، مثل الاسم ورقم الهوية أو الإقامة والمرحلة التعليمية والجهة التعليمية، بحسب الحقول المعتمدة في المنصة. ورقم الجوال إلزامي، والبريد الإلكتروني اختياري.';

    public function up(): void
    {
        $this->map(fn (string $step): string => str_starts_with($step, self::PREFIX) ? self::NEW : $step);
    }

    public function down(): void
    {
        $this->map(fn (string $step): string => $step === self::NEW ? self::PREVIOUS : $step);
    }

    private function map(Closure $change): void
    {
        foreach (DB::table('competitions')->where('student_mode', true)->whereNotNull('page_content')->get(['id', 'page_content']) as $competition) {
            $content = json_decode((string) $competition->page_content, true);
            $steps = $content['guide_steps'] ?? null;

            if (! is_array($steps)) {
                continue;
            }

            $updated = array_map(fn ($step) => is_string($step) ? $change($step) : $step, $steps);

            if ($updated !== $steps) {
                $content['guide_steps'] = $updated;
                DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
