<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * لحظة قفل نطاق الترتيب وجدول الجوائز في مسابقة الطلاب (مراجعة المشرف على 0b788d7f): تُسجَّل عند نشر نتائج آخر فرع
 * أو أول سحب تراكمي، فلا تفك إضافةُ مرحلة بعدها القفلَ.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->timestamp('ranking_locked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('competitions', fn (Blueprint $table) => $table->dropColumn('ranking_locked_at'));
    }
};
