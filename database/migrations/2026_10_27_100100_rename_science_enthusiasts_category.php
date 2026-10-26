<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * فئة «المهتمون» صار اسمها «المهتمون بالثقافة العلمية» كالمطوية (CompetitionListsSeeder يبحث بالاسم)، فيُعاد تسمية
 * الصف القائم بدل أن ينشئ البذر فئة خامسة (مراجعة المشرف على المطوية). وإن وُجدت الخامسة من بذر سابق بلا استعمال
 * (لا مسابقة مفتوحة لها ولا تسجيل ولا سؤال) حُذفت، وإلا بقيت الاثنتان للمسؤول.
 */
return new class extends Migration
{
    private const OLD = 'المهتمون';

    private const NEW = 'المهتمون بالثقافة العلمية';

    public function up(): void
    {
        $old = DB::table('competition_categories')->where('name', self::OLD)->value('id');

        if ($old === null) {
            return;
        }

        $new = DB::table('competition_categories')->where('name', self::NEW)->value('id');

        if ($new !== null && ! $this->used((int) $new)) {
            DB::table('competition_categories')->where('id', $new)->delete();
            $new = null;
        }

        if ($new === null) {
            DB::table('competition_categories')->where('id', $old)->update(['name' => self::NEW]);
        }
    }

    public function down(): void
    {
        if (DB::table('competition_categories')->where('name', self::OLD)->doesntExist()) {
            DB::table('competition_categories')->where('name', self::NEW)->update(['name' => self::OLD]);
        }
    }

    private function used(int $id): bool
    {
        return DB::table('competition_category_competition')->where('category_id', $id)->exists()
            || DB::table('competition_submissions')->where('category_id', $id)->exists()
            || DB::table('competition_questions')->where('category_id', $id)->exists();
    }
};
