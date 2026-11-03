<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * «يلزمه تعيين كلمة مرور» بعد إعادة ضبط التحقق الثنائي من الإدارة (docs/decisions/2026-11-03-2fa-admin-reset.md): تُستبدل
 * كلمة المرور بتجزئة عشوائية، ولا دخول ولا تفعيل لتطبيق المصادقة حتى تُعيَّن برابط البريد الموثق (ResetUserPassword).
 * وفارغاً لا أثر له.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('must_set_password_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_set_password_at');
        });
    }
};
