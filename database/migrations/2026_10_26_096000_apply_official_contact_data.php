<?php

use App\Models\Setting;
use App\Support\ContentCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * البيانات الرسمية للجمعية (مطوية «البراهين العلمية» وترويسة خطاباتها، 26 أكتوبر 2026 — البند 8 من المهمة) في إعدادات التواصل
 * القائمة: البريد، والجوال، وواتساب، والعنوان، والعنوان الوطني المختصر MALA4207 (إثبات العنوان من سبل) لرابط الخريطة،
 * وحساب فيسبوك باسم @ejazsa2025. ورقم الترخيص في «محتوى الرئيسية» (التذييل) بأصله.
 *
 * يُملأ الفارغ وحده، ويُستبدل ما كان بقيمته المعتمدة السابقة (العنوان ورابط فيسبوك من ملف الاستيراد)، فلا يُمس ما أدخله
 * المحرر غيرهما. وما قبله يُحفظ (contact_before_official) فيعيده الرجوع. وما يأتي به ملف الاستيراد عند الإطلاق هو نفسه.
 */
return new class extends Migration
{
    private const BACKUP = 'contact_before_official';

    private const OFFICIAL = [
        'email' => 'info@ejaz.sa',
        'phone' => '+966 55 055 6758',
        'whatsapp' => '+966550556758',
        'address' => 'مكة المكرمة — برج الساعة، الدور التاسع، بجوار مصلى الرجال',
        'short_address' => 'MALA4207',
    ];

    private const SOCIAL = [
        'x' => 'https://x.com/ejazsa2025',
        'instagram' => 'https://instagram.com/ejazsa2025',
        'youtube' => 'https://youtube.com/@ejazsa2025',
        'tiktok' => 'https://tiktok.com/@ejazsa2025',
        'telegram' => 'https://t.me/ejazsa2025',
        'facebook' => 'https://facebook.com/ejazsa2025',
    ];

    /**
     * القيم المعتمدة السابقة التي تُستبدل (ملف الاستيراد قبل البيانات الرسمية).
     */
    private const SUPERSEDED = [
        'address' => 'مكة المكرمة - برج الساعة (أبراج البيت المقابلة للمسجد الحرام) - الدور التاسع بجوار المصلى',
        'facebook' => 'https://facebook.com/profile.php?id=61595399341851',
    ];

    public function up(): void
    {
        if (DB::table('settings')->where('key', self::BACKUP)->exists()) {
            return;
        }

        $value = DB::table('settings')->where('key', 'contact')->value('value');
        $before = is_string($value) ? json_decode($value, true) : null;

        // موقع بلا إعدادات تواصل (تثبيت جديد) يأخذها من ملف الاستيراد عند الإطلاق بالقيم نفسها
        if (! is_array($before)) {
            return;
        }

        $exists = true;
        $contact = $before;

        DB::table('settings')->insert(['key' => self::BACKUP, 'value' => json_encode(['exists' => $exists, 'contact' => $contact], JSON_UNESCAPED_UNICODE),
            'created_at' => now(), 'updated_at' => now()]);

        foreach (self::OFFICIAL as $key => $official) {
            if (blank($contact[$key] ?? null) || ($contact[$key] ?? null) === (self::SUPERSEDED[$key] ?? null)) {
                $contact[$key] = $official;
            }
        }

        $social = is_array($contact['social'] ?? null) ? $contact['social'] : [];

        foreach (self::SOCIAL as $network => $official) {
            if (blank($social[$network] ?? null) || ($social[$network] ?? null) === (self::SUPERSEDED[$network] ?? null)) {
                $social[$network] = $official;
            }
        }

        $contact['social'] = $social;
        $this->save($contact, $exists);
    }

    public function down(): void
    {
        $backup = DB::table('settings')->where('key', self::BACKUP)->value('value');
        $before = is_string($backup) ? json_decode($backup, true) : null;

        if (is_array($before)) {
            if ($before['exists'] ?? false) {
                $this->save((array) ($before['contact'] ?? []), true);
            } else {
                DB::table('settings')->where('key', 'contact')->delete();
                $this->forget();
            }
        }

        DB::table('settings')->where('key', self::BACKUP)->delete();
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function save(array $contact, bool $exists): void
    {
        $value = json_encode($contact, JSON_UNESCAPED_UNICODE);

        $exists
            ? DB::table('settings')->where('key', 'contact')->update(['value' => $value, 'updated_at' => now()])
            : DB::table('settings')->insert(['key' => 'contact', 'value' => $value, 'created_at' => now(), 'updated_at' => now()]);

        $this->forget();
    }

    private function forget(): void
    {
        cache()->forget(Setting::cacheKey('contact'));
        ContentCache::flush();
    }
};
