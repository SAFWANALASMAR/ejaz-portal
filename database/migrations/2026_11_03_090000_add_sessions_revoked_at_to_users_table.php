<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * وقت إنهاء جلسات الحساب كلها من اللوحة (إعادة ضبط التحقق الثنائي — docs/decisions/2026-11-03-2fa-admin-reset.md): كل جلسة
 * بدأت قبله تُنهى عند طلبها التالي (EnsureAccountIsActive) أياً كان مخزن الجلسات، Redis أو قاعدة البيانات. وفارغاً لا أثر له.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('sessions_revoked_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('sessions_revoked_at');
        });
    }
};
