<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * خيار «للقراءة فقط — بلا تحميل» لكتب المكتبة وأبحاثها (طلب المدير التنفيذي، 30 أكتوبر 2026). والافتراضي
 * التنزيل متاح، فلا يتغير شيء من العناصر الموجودة.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->boolean('is_downloadable')->default(true);
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('is_downloadable');
        });
    }
};
