<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * تفرد الجوال على الأرقام الموثقة وحدها (decisions.md — 14 أكتوبر 2026): حساب غير موثق لا يحجز رقماً
 * لغيره. فهرس تفرد جزئي بدل فهرس التفرد الكامل، وفهرس عادي يبقى للبحث بالرقم عند الدخول.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->index('phone');
        });

        DB::statement('CREATE UNIQUE INDEX users_verified_phone_unique ON users (phone) WHERE phone_verified_at IS NOT NULL');
    }

    /**
     * لا يعمل عملياً بعد التشغيل: متى حمل حسابان غير موثقين الرقم نفسه (وهو ما يسمح به up)، رفض إنشاءُ
     * التفرد الكامل الرجوعَ بخطأ تكرار. ويلزم قبله قرار فيمن يبقى له الرقم من الحسابات غير الموثقة، ولا
     * يتخذه ترحيل (decisions.md — مراجعة التكامل، 14 أكتوبر 2026).
     */
    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS users_verified_phone_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->unique('phone');
        });
    }
};
