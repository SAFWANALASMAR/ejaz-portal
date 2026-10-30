<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مقدمة «مراحل المسابقة» في صفحة «البراهين العلمية» المخزنة بنص المدير التنفيذي حرفياً (29 أكتوبر 2026)، كما صارت في
 * database/data/brahin-competition.php للتثبيت الجديد. وما كتبه المحرر في الحقل لا يُمس. وشرط المشاركة في كل
 * المراحل باقٍ في «ضوابط المشاركة».
 */
return new class extends Migration
{
    private const SLUG = 'brahin-1448';

    private const KEY = 'rounds_intro';

    private const TEXT = 'تمر المسابقة بأربع مراحل متتابعة، وتُعقد كل مرحلة يوم الخميس بحسب الجدول الزمني والتواريخ المعتمدة لها.';

    public function up(): void
    {
        $competition = DB::table('competitions')->where('slug', self::SLUG)->first(['id', 'page_content']);

        if ($competition === null) {
            return;
        }

        $content = json_decode((string) $competition->page_content, true);
        $content = is_array($content) ? $content : [];

        if (filled($content[self::KEY] ?? null)) {
            return;
        }

        $content[self::KEY] = self::TEXT;
        DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
    }

    public function down(): void
    {
        $competition = DB::table('competitions')->where('slug', self::SLUG)->first(['id', 'page_content']);
        $content = json_decode((string) $competition?->page_content, true);

        if ($competition === null || ! is_array($content) || ($content[self::KEY] ?? null) !== self::TEXT) {
            return;
        }

        unset($content[self::KEY]);
        DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($content, JSON_UNESCAPED_UNICODE)]);
    }
};
