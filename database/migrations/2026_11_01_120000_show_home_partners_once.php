<?php

use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * «شركاؤنا» في الرئيسية تحت «البوابة بالأرقام» (ملاحظات المدير التنفيذي، 1 نوفمبر 2026، البند ١٢، واللوحة الأولى من ملف التصور).
 * المفتاح ظاهر افتراضياً منذ النسخة 63، لكن ترحيل 26 أكتوبر (hide_legacy_home_sections_once) أطفأه صراحةً في كل بيئة كانت لها
 * إعدادات، ومنها رابط العرض. فيُظهر مرة واحدة هنا، ثم يبقى بيد اللوحة («إعدادات الموقع» ← «الرئيسية»). وبلا شركاء منشورين لا يظهر
 * شيء (BuildHomePage). والقيمة السابقة تُحفظ (home_partners_before)، فيعيدها الرجوع.
 */
return new class extends Migration
{
    private const BACKUP = 'home_partners_before';

    public function up(): void
    {
        $home = $this->home();

        if ($home === null || ($home['show_partners'] ?? true) !== false || DB::table('settings')->where('key', self::BACKUP)->exists()) {
            return;
        }

        DB::table('settings')->insert(['key' => self::BACKUP, 'value' => json_encode(['show_partners' => false]), 'created_at' => now(), 'updated_at' => now()]);
        $this->save([...$home, 'show_partners' => true]);
    }

    public function down(): void
    {
        $home = $this->home();

        if ($home !== null && DB::table('settings')->where('key', self::BACKUP)->exists()) {
            $this->save([...$home, 'show_partners' => false]);
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
