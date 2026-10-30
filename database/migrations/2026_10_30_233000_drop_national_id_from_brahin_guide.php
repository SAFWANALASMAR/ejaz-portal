<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * الخطوة الثالثة في دليل دخول البراهين المخزّن بلا «رقم الهوية أو الإقامة»: المنصة لا تطلبه (قرار صفوان 19 أكتوبر 2026،
 * و30 أكتوبر للنص). في مسابقات الطلاب وحدها، والبذرة صُحّحت للتثبيت الجديد، ومطوية الجمعية (PDF) لا تُمس.
 */
return new class extends Migration
{
    private const OLD = 'مثل الاسم ورقم الهوية أو الإقامة والمرحلة التعليمية';

    private const NEW = 'مثل الاسم والمرحلة التعليمية';

    public function up(): void
    {
        $this->replace(self::OLD, self::NEW);
    }

    public function down(): void
    {
        $this->replace(self::NEW, self::OLD);
    }

    private function replace(string $from, string $to): void
    {
        foreach (DB::table('competitions')->where('student_mode', true)->whereNotNull('page_content')->get(['id', 'page_content']) as $competition) {
            $content = json_decode((string) $competition->page_content, true);
            $steps = $content['guide_steps'] ?? null;

            if (! is_array($steps)) {
                continue;
            }

            $updated = array_map(fn ($step) => is_string($step) ? str_replace($from, $to, $step) : $step, $steps);

            if ($updated !== $steps) {
                $content['guide_steps'] = $updated;
                DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
            }
        }
    }
};
