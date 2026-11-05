<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * الجوال فريد على كل الحسابات، موثقة أو لا، فهو معرّف الدخول (قرار صفوان، 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md).
 * ويحل محل التفرد على الموثق وحده (14 أكتوبر 2026).
 *
 * والرقم المكرر يبقى لحساب واحد: الموثق، ثم من لا معرّف دخول له غيره (لا اسم مستخدم ولا بريد)، ثم الأقدم. ويُفرَّغ من
 * البقية، وكلها غير موثقة الرقم (التفرد الجزئي يمنع موثقَين)، ولها اسم مستخدم أو بريد تدخل به. وأرقام حساباتها في السجل.
 */
return new class extends Migration
{
    public function up(): void
    {
        $cleared = DB::select(<<<'SQL'
            UPDATE users SET phone = NULL, phone_verified_at = NULL
            WHERE id IN (
                SELECT id FROM (
                    SELECT id, ROW_NUMBER() OVER (
                        PARTITION BY phone
                        ORDER BY phone_verified_at IS NULL, (username IS NULL AND email IS NULL) DESC, id
                    ) AS position
                    FROM users WHERE phone IS NOT NULL
                ) ranked WHERE position > 1
            )
            RETURNING id
            SQL);

        if ($cleared !== []) {
            Log::warning('Cleared duplicate phone numbers before making them unique.', ['user_ids' => array_column($cleared, 'id')]);
        }

        DB::statement('DROP INDEX IF EXISTS users_verified_phone_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->unique('phone');
        });
    }

    /**
     * يعيد التفرد على الموثق وحده، ولا يعيد الأرقام المفرَّغة (أرقام حساباتها في السجل).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->index('phone');
        });

        DB::statement('CREATE UNIQUE INDEX users_verified_phone_unique ON users (phone) WHERE phone_verified_at IS NOT NULL');
    }
};
