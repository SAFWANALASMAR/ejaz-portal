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
 * والرقم المكرر يبقى لحساب واحد: الموثق، ثم من لا معرّف دخول له غيره، ثم الأقدم. ومعرّف الدخول اسم مستخدم، أو بريد موثق،
 * أو بريد غير موثق لا يحمله حساب آخر (المشترك لا يُدخل به — User::soleHolder). ويُفرَّغ من البقية، وكلها غير موثقة الرقم
 * (التفرد الجزئي يمنع موثقَين)، ويُحفظ رقم كل منها في phone_login_cleared_phones ليعيده الرجوع.
 *
 * و pending_phone: رقم يحمله حساب غير موثق، سجّل به صاحبه والتوثيق مفعّل، فلا يصير رقمه إلا بعد رمز التحقق، وحينها يُنزع
 * من الحساب غير الموثق (VerifyPhoneCode — مراجعة المشرف على #180، المهمة ١).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('phone_login_cleared_phones', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->primary();
            $table->string('phone');
        });

        $cleared = DB::affectingStatement(<<<'SQL'
            WITH ranked AS (
                SELECT id, phone, ROW_NUMBER() OVER (
                    PARTITION BY phone
                    ORDER BY phone_verified_at IS NULL, has_identifier, id
                ) AS position
                FROM (
                    SELECT u.id, u.phone, u.phone_verified_at,
                        (u.username IS NOT NULL OR (u.email IS NOT NULL AND u.email_verified_at IS NOT NULL)
                            OR (u.email IS NOT NULL AND NOT EXISTS (SELECT 1 FROM users o WHERE o.email = u.email AND o.id <> u.id))) AS has_identifier
                    FROM users u
                    WHERE u.phone IS NOT NULL
                ) holders
            ), cleared AS (
                UPDATE users SET phone = NULL, phone_verified_at = NULL
                FROM ranked
                WHERE users.id = ranked.id AND ranked.position > 1
                RETURNING users.id, ranked.phone
            )
            INSERT INTO phone_login_cleared_phones (user_id, phone) SELECT id, phone FROM cleared
            SQL);

        if ($cleared > 0) {
            Log::warning('Cleared duplicate phone numbers before making them unique.', ['accounts' => $cleared]);
        }

        DB::statement('DROP INDEX IF EXISTS users_verified_phone_unique');

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['phone']);
            $table->unique('phone');
            $table->string('pending_phone')->nullable();
        });
    }

    /**
     * يعيد التفرد على الموثق وحده، والأرقام المفرَّغة إلى حساباتها (ما بقي رقمها فارغاً).
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('pending_phone');
            $table->dropUnique(['phone']);
            $table->index('phone');
        });

        DB::statement('CREATE UNIQUE INDEX users_verified_phone_unique ON users (phone) WHERE phone_verified_at IS NOT NULL');
        DB::statement('UPDATE users SET phone = cleared.phone FROM phone_login_cleared_phones cleared WHERE users.id = cleared.user_id AND users.phone IS NULL');

        Schema::drop('phone_login_cleared_phones');
    }
};
