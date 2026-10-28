<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * القوائم بملاحظات المدير التنفيذي (عبر صفوان، 25 أكتوبر 2026):
 * - «المركز الإعلامي»: الأخبار والإعلانات وحدهما. فتُضاف «الإعلانات»، وتُنقل «مكتبة إعجاز» و«مكتبة الفيديو» إلى قائمتها،
 *   وتُخفى «مكتبة الصور» و«الشركاء» (تبقى صفحاتهما، وتُظهر من «القائمة» متى أُريد).
 * - «مكتبة إعجاز» عنصر رئيسي بعد «المركز الإعلامي»، وتحته: المكتبة المرئية، والمكتبة الصوتية، والكتب، والأبحاث والدراسات.
 * يمس ما كان بقيمه الافتراضية وحده (بعنوانه ورابطه معاً، MenuSeeder)، فلا يغيّر ما عدّله المحرر. والتعديل بعدها من اللوحة.
 * والرجوع يعيد ما حذفه وما أخفاه بقيمه الافتراضية (مراجعة المشرف المبكرة، ١٢).
 */
return new class extends Migration
{
    private const LIBRARY = [
        ['المكتبة المرئية', '/library/videos'],
        ['المكتبة الصوتية', '/library/audio'],
        ['الكتب', '/library/books'],
        ['الأبحاث والدراسات', '/library/research'],
    ];

    /**
     * ما نُقل من «المركز الإعلامي» إلى «مكتبة إعجاز»: العنوان والرابط وترتيبه الافتراضي.
     */
    private const MOVED = [
        ['مكتبة إعجاز', '/library', 1],
        ['مكتبة الفيديو', '/library/videos', 3],
    ];

    /**
     * ما أُخفي من «المركز الإعلامي».
     */
    private const HIDDEN = [
        ['مكتبة الصور', '/media/albums'],
        ['الشركاء', '/media/partners'],
    ];

    public function up(): void
    {
        $media = DB::table('menu_items')->whereNull('parent_id')->where('url', '/media')->first();

        if ($media !== null) {
            foreach (self::MOVED as [$label, $url]) {
                DB::table('menu_items')->where('parent_id', $media->id)->where('label', $label)->where('url', $url)->delete();
            }

            foreach (self::HIDDEN as [$label, $url]) {
                DB::table('menu_items')->where('parent_id', $media->id)->where('label', $label)->where('url', $url)
                    ->update(['is_visible' => false, 'updated_at' => now()]);
            }

            if (! DB::table('menu_items')->where('parent_id', $media->id)->where('url', '/media/announcements')->exists()) {
                DB::table('menu_items')->insert(['parent_id' => $media->id, 'label' => 'الإعلانات', 'url' => '/media/announcements',
                    'opens_in_new_tab' => false, 'is_visible' => true, 'sort' => 1, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        if (DB::table('menu_items')->whereNull('parent_id')->where('url', '/library')->doesntExist() && $media !== null) {
            // بعد المركز الإعلامي: ما بعده يتأخر خطوة
            DB::table('menu_items')->whereNull('parent_id')->where('sort', '>', $media->sort)->increment('sort');

            $library = DB::table('menu_items')->insertGetId(['parent_id' => null, 'label' => 'مكتبة إعجاز', 'url' => '/library',
                'opens_in_new_tab' => false, 'is_visible' => true, 'sort' => $media->sort + 1, 'created_at' => now(), 'updated_at' => now()]);

            foreach (self::LIBRARY as $sort => [$label, $url]) {
                DB::table('menu_items')->insert(['parent_id' => $library, 'label' => $label, 'url' => $url,
                    'opens_in_new_tab' => false, 'is_visible' => true, 'sort' => $sort, 'created_at' => now(), 'updated_at' => now()]);
            }
        }

        Cache::forget(MenuItem::CACHE_KEY);
    }

    public function down(): void
    {
        $media = DB::table('menu_items')->whereNull('parent_id')->where('url', '/media')->first();
        $library = DB::table('menu_items')->whereNull('parent_id')->where('url', '/library')->where('label', 'مكتبة إعجاز')->first();

        if ($library !== null) {
            DB::table('menu_items')->where('parent_id', $library->id)->delete();
            DB::table('menu_items')->where('id', $library->id)->delete();
            DB::table('menu_items')->whereNull('parent_id')->where('sort', '>', $library->sort)->decrement('sort');
        }

        DB::table('menu_items')->where('url', '/media/announcements')->where('label', 'الإعلانات')->delete();

        if ($media !== null) {
            foreach (self::MOVED as [$label, $url, $sort]) {
                if (DB::table('menu_items')->where('parent_id', $media->id)->where('url', $url)->doesntExist()) {
                    DB::table('menu_items')->insert(['parent_id' => $media->id, 'label' => $label, 'url' => $url,
                        'opens_in_new_tab' => false, 'is_visible' => true, 'sort' => $sort, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            foreach (self::HIDDEN as [$label, $url]) {
                DB::table('menu_items')->where('parent_id', $media->id)->where('label', $label)->where('url', $url)
                    ->update(['is_visible' => true, 'updated_at' => now()]);
            }
        }

        Cache::forget(MenuItem::CACHE_KEY);
    }
};
