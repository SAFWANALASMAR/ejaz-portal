<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «إظهار مواعيد المراحل» في صفحة مسابقة الطلاب العامة (ملاحظة المدير التنفيذي، 29 أكتوبر 2026): مطفأ افتراضياً،
 * فبطاقات المراحل بلا تواريخ حتى تُعلَن من اللوحة. والنوافذ تعمل بالتواريخ المخزنة في الحالين.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->boolean('show_round_dates')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->dropColumn('show_round_dates');
        });
    }
};
