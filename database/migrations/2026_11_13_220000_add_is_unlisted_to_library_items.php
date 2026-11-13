<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * كتاب منشور لا يظهر في قوائم المكتبة (قرار صفوان، 13 نوفمبر 2026: «مكانه ليس المكتبة» —
 * docs/decisions/2026-11-13-fix-brahin-guide-outside-library.md): يُفتح برابطه وحده، مثل دليل مسابقة البراهين من صفحتها.
 * مطفأ افتراضياً، فالعناصر القائمة كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('library_items', function (Blueprint $table): void {
            $table->boolean('is_unlisted')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('library_items', function (Blueprint $table): void {
            $table->dropColumn('is_unlisted');
        });
    }
};
