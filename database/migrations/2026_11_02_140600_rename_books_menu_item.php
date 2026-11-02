<?php

use App\Models\MenuItem;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * عنصر «الكتب» تحت «مكتبة إعجاز» يصير «الكتب والمؤلفات»، اسم القسم نفسه (البند R9، docs/decisions/2026-11-02-library-four-sections.md).
 * القائمة المنسدلة تأخذ اسم عنصر المحرر إن وُجد برابط القسم (MenuSections)، فبقي فيها «الكتب» من ترحيل 25 أكتوبر. ويمس العنصر
 * بعنوانه الافتراضي ورابطه معاً وحده، فلا يغيّر ما عدّله المحرر. والرجوع يعيده.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->rename('الكتب', 'الكتب والمؤلفات');
    }

    public function down(): void
    {
        $this->rename('الكتب والمؤلفات', 'الكتب');
    }

    private function rename(string $from, string $to): void
    {
        $library = DB::table('menu_items')->whereNull('parent_id')->where('url', '/library')->value('id');

        if ($library === null) {
            return;
        }

        DB::table('menu_items')->where('parent_id', $library)->where('label', $from)->where('url', '/library/books')
            ->update(['label' => $to, 'updated_at' => now()]);

        Cache::forget(MenuItem::CACHE_KEY);
    }
};
