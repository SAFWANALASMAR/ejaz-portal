<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * بطاقة لوحة المستفيد لأدوار بعينها (AccountCardAudience::Roles — مراجعة المشرف، 26 أكتوبر 2026): قائمة أسماء الأدوار.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('account_cards', function (Blueprint $table) {
            $table->json('roles')->nullable()->after('audience');
        });
    }

    public function down(): void
    {
        Schema::table('account_cards', function (Blueprint $table) {
            $table->dropColumn('roles');
        });
    }
};
