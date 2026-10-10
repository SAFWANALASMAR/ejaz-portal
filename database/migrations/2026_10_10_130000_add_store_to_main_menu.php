<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * «المتجر» في القائمة الرئيسية (قرار صفوان، 10 أكتوبر 2026): إلى /store الذي يحوّل إلى متجر سلة،
 * ويفتح في تبويب جديد. يُضاف قبل «تواصل معنا» مرة واحدة إن لم يوجد، وتعديله بعد ذلك من اللوحة.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('menu_items')->exists() || DB::table('menu_items')->where('url', '/store')->exists()) {
            return;
        }

        $contact = DB::table('menu_items')->whereNull('parent_id')->where('url', '/contact')->value('sort');
        $sort = $contact ?? ((int) DB::table('menu_items')->whereNull('parent_id')->max('sort') + 1);

        DB::table('menu_items')->whereNull('parent_id')->where('sort', '>=', $sort)->increment('sort');
        DB::table('menu_items')->insert([
            'label' => 'المتجر',
            'url' => '/store',
            'opens_in_new_tab' => true,
            'is_visible' => true,
            'sort' => $sort,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Cache::forget(MenuItem::CACHE_KEY);
    }

    public function down(): void
    {
        // ما أضافه هذا الترحيل وحده (العنوان والرابط معاً)
        DB::table('menu_items')->whereNull('parent_id')->where('url', '/store')->where('label', 'المتجر')->delete();
        Cache::forget(MenuItem::CACHE_KEY);
    }
};
