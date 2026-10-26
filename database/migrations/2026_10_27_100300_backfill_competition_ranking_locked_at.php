<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * لحظة قفل الترتيب (ranking_locked_at) لمسابقات الطلاب التي نُشرت فروعها كلها أو سُحب في ترتيبها التراكمي قبل ترحيل
 * العمود (مراجعة المشرف على #67)، فلا تفك مرحلة تُضاف لاحقاً قفلها. ولحظتها أول ما قفلها: أول سحب تراكمي، أو نشر آخر
 * فرع إن نُشرت كلها (LEAST يتجاوز الفارغ). ومسابقات الطلاب وحدها، وما قُفل من قبل لا يُمس، فالتشغيل الثاني لا يغيّر شيئاً.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            UPDATE competitions c
            SET ranking_locked_at = LEAST(
                (SELECT MIN(d.executed_at) FROM competition_draws d WHERE d.competition_id = c.id AND d.round_id IS NULL AND d.gender IS NOT NULL),
                CASE WHEN NOT EXISTS (SELECT 1 FROM competition_rounds r WHERE r.competition_id = c.id AND r.results_published_at IS NULL)
                    THEN (SELECT MAX(r.results_published_at) FROM competition_rounds r WHERE r.competition_id = c.id)
                END
            )
            WHERE c.student_mode AND c.ranking_locked_at IS NULL AND (
                EXISTS (SELECT 1 FROM competition_draws d WHERE d.competition_id = c.id AND d.round_id IS NULL AND d.gender IS NOT NULL)
                OR (
                    EXISTS (SELECT 1 FROM competition_rounds r WHERE r.competition_id = c.id)
                    AND NOT EXISTS (SELECT 1 FROM competition_rounds r WHERE r.competition_id = c.id AND r.results_published_at IS NULL)
                )
            )
            SQL);
    }

    public function down(): void
    {
        // بيانات محسوبة، ولا يُعرف ما كان فارغاً قبلها
    }
};
