<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «لا تحفظ قراءاتي» (قرار صفوان، 2 نوفمبر 2026، ن٢ على #117): وقت إيقاف صاحب الحساب حفظ تقدّمه في المكتبة، وفارغاً يبقى الحفظ
 * التلقائي الافتراضي (docs/decisions/2026-11-01-my-reading.md).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('reading_tracking_disabled_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('reading_tracking_disabled_at');
        });
    }
};
