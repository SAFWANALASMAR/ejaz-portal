<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * أول توثيق من سجل العمليات لمن بقي بلا first_verified_at (المراجعة الثانية للتكامل، 15 أكتوبر 2026): ترحيل
 * 2026_10_14_100000 ملأه من التاريخين الحاليين وحدهما، فالموثق الذي غيّر بريده أو جواله قبله فقد توثيقه
 * الحالي بقي فارغاً. وسجل العمليات يحفظ القيم القديمة والجديدة لحقلي التوثيق (User::getActivitylogOptions)،
 * فيؤخذ أقدمها. ولا يُمس من له قيمة.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE users
            SET first_verified_at = earliest.verified_at
            FROM (
                SELECT log.subject_id, MIN(stamp.value::timestamptz AT TIME ZONE 'UTC') AS verified_at
                FROM activity_log log
                CROSS JOIN LATERAL (VALUES
                    (log.attribute_changes->'attributes'->>'email_verified_at'),
                    (log.attribute_changes->'attributes'->>'phone_verified_at'),
                    (log.attribute_changes->'old'->>'email_verified_at'),
                    (log.attribute_changes->'old'->>'phone_verified_at')
                ) AS stamp(value)
                WHERE log.subject_type = ? AND stamp.value ~ '^\d{4}-\d{2}-\d{2}'
                GROUP BY log.subject_id
            ) AS earliest
            WHERE users.id = earliest.subject_id AND users.first_verified_at IS NULL
            SQL, [User::class]);
    }

    public function down(): void
    {
        // تعبئة بيانات لا يُميَّز ما ملأته من غيره، فلا رجوع عنها، ولا يضر بقاؤها
    }
};
