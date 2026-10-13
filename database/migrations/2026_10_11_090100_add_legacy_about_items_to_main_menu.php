<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * قائمة «عن الجمعية» كما في الموقع السابق (أرشيف الموقع — القائمة الرئيسية): الوثائق الرسمية
 * والآليات بمسمياتها في القائمة، و«اللوائح والسياسات» إلى قسمها في الحوكمة (الرأس بمستويين). تُضاف
 * مرة واحدة إن لم يكن لـ«عن الجمعية» عناصر فرعية. وعناصر الوثائق مخفية، لأن الوثائق تُستورد مسودات:
 * يُظهرها المحرر من «القائمة» بعد نشرها، فلا يظهر رابط إلى 404.
 *
 * ويُصحَّح رابط كل عنصر بقي على مسار لا وجود له في البوابة (من القائمة الافتراضية) إلى مساره المبني،
 * إن بقي كما كان. وتعديلها بعد ذلك من اللوحة.
 */
return new class extends Migration
{
    private const ABOUT_ITEMS = [
        ['عن الجمعية', '/about'],
        ['شهادة ترخيص الجمعية', '/governance/license-certificate'],
        ['التوجه الاستراتيجي', '/about'],
        ['قرار تسجيل الجمعية', '/governance/registration-decision'],
        ['قرار تشكيل مجلس الإدارة', '/governance/board-formation-decision'],
        ['الفروع', '/governance/no-branches-statement'],
        ['اللوائح والسياسات', '/governance?category=policies'],
        ['آلية التحقق', '/governance/beneficiary-eligibility-mechanism'],
        ['آلية استرداد', '/governance/donation-refund-mechanism'],
        ['وثيقة الافصاح', '/governance/disclosure-document'],
    ];

    /**
     * الرابط القديم ← [الصحيح، وعنوان العنصر في القائمة الافتراضية].
     */
    private const FIXED_URLS = [
        '/news' => ['/media/news', 'الأخبار'],
        '/media/photos' => ['/media/albums', 'مكتبة الصور'],
        '/media/videos' => ['/library/videos', 'مكتبة الفيديو'],
        '/partners' => ['/media/partners', 'الشركاء'],
    ];

    public function up(): void
    {
        foreach (self::FIXED_URLS as $from => [$to]) {
            DB::table('menu_items')->where('url', $from)->update(['url' => $to, 'updated_at' => now()]);
        }

        $about = DB::table('menu_items')->whereNull('parent_id')->where('url', '/about')->value('id');

        if ($about !== null && ! DB::table('menu_items')->where('parent_id', $about)->exists()) {
            foreach (self::ABOUT_ITEMS as $sort => [$label, $url]) {
                DB::table('menu_items')->insert([
                    'parent_id' => $about,
                    'label' => $label,
                    'url' => $url,
                    'opens_in_new_tab' => false,
                    'is_visible' => ! str_starts_with($url, '/governance/'),
                    'sort' => $sort + 1,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        Cache::forget(MenuItem::CACHE_KEY);
    }

    public function down(): void
    {
        $about = DB::table('menu_items')->whereNull('parent_id')->where('url', '/about')->value('id');

        if ($about !== null) {
            foreach (self::ABOUT_ITEMS as [$label, $url]) {
                DB::table('menu_items')->where('parent_id', $about)->where('label', $label)->where('url', $url)->delete();
            }
        }

        // ما غيّره up() وحده: العنصر الافتراضي بعنوانه، لا عنصر أضافه المحرر إلى المسار نفسه
        foreach (self::FIXED_URLS as $from => [$to, $label]) {
            DB::table('menu_items')->where('url', $to)->where('label', $label)->update(['url' => $from, 'updated_at' => now()]);
        }

        Cache::forget(MenuItem::CACHE_KEY);
    }
};
