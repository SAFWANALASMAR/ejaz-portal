<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «المواعيد تُعلن لاحقاً» للمسابقة (قرار صفوان، 12 نوفمبر 2026 — docs/decisions/2026-11-12-feature-competition-schedule-pending.md):
 * تُنشر صفحتها بلا مواعيد والتسجيل مغلق حتى تعتمد الجمعية المواعيد. مطفأ افتراضياً، فالمسابقات القائمة كما هي.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->boolean('schedule_pending')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table): void {
            $table->dropColumn('schedule_pending');
        });
    }
};
