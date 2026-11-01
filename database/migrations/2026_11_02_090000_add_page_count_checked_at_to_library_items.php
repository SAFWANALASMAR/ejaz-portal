<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وقت محاولة عدّ صفحات الملف الحالي (CountLibraryPages)، نجح العد أو تعذّر. به لا تُعاد المهمة مع كل بلاغ لكتاب تعذّر عدّه،
 * بل عند تغيّر الملف وحده (مراجعة المشرف الأمنية على #117، م١ — docs/decisions/2026-11-01-my-reading.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->timestamp('page_count_checked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table) {
            $table->dropColumn('page_count_checked_at');
        });
    }
};
