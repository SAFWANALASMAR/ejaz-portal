<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * فئة «المهتمون» صار اسمها «المهتمون بالثقافة العلمية» كالمطوية (CompetitionListsSeeder يبحث بالاسم)، فيُعاد تسمية
 * الصف القائم بدل فئة خامسة (مراجعة المشرف على المطوية). وإن وُجدت الخامسة من بذر سابق دُمجت في القديمة: يُنقل إليها
 * فتحُ المسابقات والتسجيلات والأسئلة والنصوص، ثم تُحذف الجديدة. إلا إن أشار إليها سجل سحب (ثابت لا يُعدَّل)، فتبقيان.
 */
return new class extends Migration
{
    private const OLD = 'المهتمون';

    private const NEW = 'المهتمون بالثقافة العلمية';

    /**
     * الجداول التي تشير إلى الفئة ويُنقل فيها المعرّف.
     */
    private const REFERENCES = ['competition_submissions', 'competition_questions', 'competition_passages'];

    public function up(): void
    {
        $old = DB::table('competition_categories')->where('name', self::OLD)->value('id');

        if ($old === null) {
            return;
        }

        $new = DB::table('competition_categories')->where('name', self::NEW)->value('id');

        if ($new !== null) {
            if (DB::table('competition_draws')->where('category_id', $new)->exists()) {
                return;
            }

            // فتح المسابقات: يُضاف للقديمة ما ليس لها، ثم تُحذف روابط الجديدة
            $opened = DB::table('competition_category_competition')->where('category_id', $new)->pluck('competition_id');

            foreach ($opened as $competitionId) {
                DB::table('competition_category_competition')->insertOrIgnore(['competition_id' => $competitionId, 'category_id' => $old]);
            }

            DB::table('competition_category_competition')->where('category_id', $new)->delete();

            foreach (self::REFERENCES as $table) {
                DB::table($table)->where('category_id', $new)->update(['category_id' => $old]);
            }

            DB::table('competition_categories')->where('id', $new)->delete();
        }

        DB::table('competition_categories')->where('id', $old)->update(['name' => self::NEW]);
    }

    public function down(): void
    {
        if (DB::table('competition_categories')->where('name', self::OLD)->doesntExist()) {
            DB::table('competition_categories')->where('name', self::NEW)->update(['name' => self::OLD]);
        }
    }
};
