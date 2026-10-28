<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تفرد البريد على الموثق وحده كالجوال (مراجعة المشرف المبكرة لملاحظات المدير التنفيذي، 26 أكتوبر 2026): التوثيق
 * مطفأ افتراضياً، فلا يحجز حساب غير موثق بريد صاحبه فلا يسجل ولا يستعيد. فهرس تفرد جزئي بدل الكامل، وفهرس عادي
 * يبقى للبحث بالبريد عند الدخول. وأحقية البريد للموثق (User::markEmailAsVerified).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
        });

        DB::statement('CREATE UNIQUE INDEX users_verified_email_unique ON users (email) WHERE email_verified_at IS NOT NULL');
    }

    /**
     * لا يعمل متى حمل حسابان غير موثقين البريد نفسه (وهو ما يسمح به up)، كترحيل الجوال
     * (2026_10_14_090200_make_phone_unique_only_when_verified).
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_verified_email_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }
};
