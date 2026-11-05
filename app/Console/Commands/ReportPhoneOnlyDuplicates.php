<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * قبل ترحيل تفرد الجوال (2026_11_05_010000_make_phone_unique_for_all_accounts): معرّفات الحسابات التي سيفرّغ الترحيل جوالها
 * وهو معرّف دخولها الوحيد، فلا تدخل بعده حتى يُضاف لها بريد أو جوال من اللوحة. بالترتيب نفسه الذي يتبعه الترحيل، وبلا الأرقام
 * (البلاغ 185، L2). وبعد الترحيل لا تكرار، فلا يطبع شيئاً.
 */
class ReportPhoneOnlyDuplicates extends Command
{
    protected $signature = 'ejaz:phone-only-duplicates';

    protected $description = 'قبل ترحيل تفرد الجوال: معرّفات الحسابات التي سيفرّغ جوالها وهو معرّف دخولها الوحيد';

    public function handle(): int
    {
        $ids = array_map(fn (object $row): int => (int) $row->id, DB::select(<<<'SQL'
            SELECT id FROM (
                SELECT id, has_identifier, ROW_NUMBER() OVER (
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
            ) ranked
            WHERE position > 1 AND NOT has_identifier
            ORDER BY id
            SQL));

        if ($ids === []) {
            $this->info('لا حساب يفقد معرّف دخوله الوحيد بالترحيل.');

            return self::SUCCESS;
        }

        $this->warn('حسابات سيفرّغ الترحيل جوالها وهو معرّف دخولها الوحيد ('.count($ids).'):');
        $this->line(implode(', ', $ids));

        return self::SUCCESS;
    }
}
