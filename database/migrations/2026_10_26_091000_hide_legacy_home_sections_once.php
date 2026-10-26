<?php

use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * مقاطع الرئيسية السابقة مخفية افتراضياً منذ ملاحظات المدير التنفيذي (25 أكتوبر 2026)، والإحصائيات منها (البند 7). والموقع
 * الذي حُفظت إعداداته قبلها يحمل قيمها true، فتبقى ظاهرة. فتُطفأ مرة واحدة هنا (قرار صفوان: الرئيسية كما طلب المدير)، ثم
 * تبقى بيد اللوحة. وبقية المفاتيح كما هي.
 *
 * والقيم السابقة تُحفظ في الإعدادات (home_legacy_before)، فيعيدها الرجوع كما كانت (مراجعة المشرف، 26 أكتوبر 2026).
 */
return new class extends Migration
{
    private const LEGACY = ['show_hero', 'show_video', 'show_stats', 'show_shortcuts', 'show_initiatives', 'show_videos', 'show_partners'];

    private const BACKUP = 'home_legacy_before';

    public function up(): void
    {
        $home = $this->home();

        if ($home === null || DB::table('settings')->where('key', self::BACKUP)->exists()) {
            return;
        }

        // ما لم يكن مفتاحه محفوظاً يُحفظ null، فيعود غائباً كما كان
        $before = array_combine(self::LEGACY, array_map(fn (string $key): mixed => $home[$key] ?? null, self::LEGACY));

        DB::table('settings')->insert(['key' => self::BACKUP, 'value' => json_encode($before), 'created_at' => now(), 'updated_at' => now()]);
        $this->save([...$home, ...array_fill_keys(self::LEGACY, false)]);
    }

    public function down(): void
    {
        $backup = DB::table('settings')->where('key', self::BACKUP)->value('value');
        $before = is_string($backup) ? json_decode($backup, true) : null;
        $home = $this->home();

        if (is_array($before) && $home !== null) {
            foreach (self::LEGACY as $key) {
                if (array_key_exists($key, $before) && $before[$key] !== null) {
                    $home[$key] = $before[$key];
                } else {
                    unset($home[$key]);
                }
            }

            $this->save($home);
        }

        DB::table('settings')->where('key', self::BACKUP)->delete();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function home(): ?array
    {
        $value = DB::table('settings')->where('key', 'home')->value('value');
        $home = is_string($value) ? json_decode($value, true) : null;

        return is_array($home) ? $home : null;
    }

    /**
     * @param  array<string, mixed>  $home
     */
    private function save(array $home): void
    {
        DB::table('settings')->where('key', 'home')->update(['value' => json_encode($home, JSON_UNESCAPED_UNICODE), 'updated_at' => now()]);
        cache()->forget(Setting::cacheKey('home'));
        ContentCache::flush();
    }
};
