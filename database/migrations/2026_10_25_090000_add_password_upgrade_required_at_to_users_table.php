<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * مطالبة تغيير كلمة المرور على الحساب لا في الجلسة (مراجعة المشرف على #64، 25 أكتوبر 2026): يضعها الدخول بكلمة مرور لا
 * تطابق السياسة، وترفعها كلمة مرور جديدة. فتبقى مع «تذكّرني» والجلسات الأخرى، ولا تنتقل إلى حساب آخر في الجلسة نفسها.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('password_upgrade_required_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('password_upgrade_required_at');
        });
    }
};
