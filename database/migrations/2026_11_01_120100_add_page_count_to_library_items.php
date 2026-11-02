<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * عدد صفحات الكتاب من ملفه، تحسبه مهمة في الطابور عند حفظ الملف (CountLibraryPages)، فلا يُقرأ الملف على مسار بلاغ «قراءاتي»
 * (docs/decisions/2026-11-01-my-reading.md). وفارغاً حتى يُحسب.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->unsignedInteger('page_count')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('page_count');
        });
    }
};
