<?php

use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مقاطع الرئيسية السابقة مخفية افتراضياً منذ ملاحظات المدير التنفيذي (25 أكتوبر 2026)، والإحصائيات منها (البند 7).
 * والموقع الذي حُفظت إعداداته قبلها يحمل قيمها true، فتبقى ظاهرة. فتُطفأ مرة واحدة هنا، ثم تبقى بيد اللوحة
 * (مراجعة المشرف المبكرة، ٦). وبقية المفاتيح كما هي.
 */
return new class extends Migration
{
    private const LEGACY = ['show_hero', 'show_video', 'show_stats', 'show_shortcuts', 'show_initiatives', 'show_videos', 'show_partners'];

    public function up(): void
    {
        $value = DB::table('settings')->where('key', 'home')->value('value');

        if (! is_string($value)) {
            return;
        }

        $home = json_decode($value, true);

        if (! is_array($home)) {
            return;
        }

        DB::table('settings')->where('key', 'home')->update([
            'value' => json_encode([...$home, ...array_fill_keys(self::LEGACY, false)], JSON_UNESCAPED_UNICODE),
            'updated_at' => now(),
        ]);

        cache()->forget(Setting::cacheKey('home'));
        ContentCache::flush();
    }

    /**
     * لا رجوع: القيم السابقة لا تُعرف بعد الإطفاء، وتُعاد من اللوحة.
     */
    public function down(): void {}
};
