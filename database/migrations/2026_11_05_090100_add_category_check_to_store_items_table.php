<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * تصنيف عنصر المتجر من قيم StoreItemCategory وحدها في القاعدة أيضاً (قرار المشرف على #179، البند 5)، بترحيل جديد لا بتعديل
 * ترحيل الجدول بعد دمجه. والقيم ثابتة هنا: إضافة تصنيف للتعداد تلزمها ترحيل يوسّع القيد.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE store_items ADD CONSTRAINT store_items_category_check CHECK (category IN ('donation', 'publication'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE store_items DROP CONSTRAINT IF EXISTS store_items_category_check');
    }
};
