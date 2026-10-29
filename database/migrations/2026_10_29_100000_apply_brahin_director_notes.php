<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * ملاحظات المدير التنفيذي على صفحة مسابقة «البراهين العلمية» (29 أكتوبر 2026، قرار صفوان: كما قال المدير) على البيانات
 * المخزنة، كما صارت في البذرتين (CompetitionListsSeeder وdatabase/data/brahin-competition.php):
 * - فئة «المرحلة المتوسطة» تُغلق في كل المسابقات، وتُحذف بصفوفها إن لم يشر إليها تسجيل ولا سؤال ولا نص ولا سحب. وإلا
 *   تبقى مغلقة، فلا ينكسر تسجيل قائم ولا سجل سحب ثابت.
 * - أسماء المراحل «الفرع الأول…الرابع» تصير «المرحلة الأولى…الرابعة»، وما عدّله المحرر باسم آخر لا يُمس.
 * - في نصوص صفحة مسابقات الطلاب: يُحذف بند «الالتزام بضوابط المسابقة وتوجيهات لجنة التحكيم.» من الضوابط، و«الفروع»
 *   تصير «المراحل».
 * - حقل «الجهة التعليمية / المدرسة» إلزامي.
 */
return new class extends Migration
{
    private const STAGE = 'المرحلة المتوسطة';

    private const REFERENCES = ['competition_submissions', 'competition_questions', 'competition_passages', 'competition_draws'];

    private const ROUNDS = [
        'الفرع الأول' => 'المرحلة الأولى',
        'الفرع الثاني' => 'المرحلة الثانية',
        'الفرع الثالث' => 'المرحلة الثالثة',
        'الفرع الرابع' => 'المرحلة الرابعة',
    ];

    private const JUDGING_RULE = 'الالتزام بضوابط المسابقة وتوجيهات لجنة التحكيم.';

    private const SCHOOL_FIELD = 'الجهة التعليمية / المدرسة';

    public function up(): void
    {
        $stage = DB::table('competition_categories')->where('name', self::STAGE)->value('id');

        if ($stage !== null) {
            DB::table('competition_category_competition')->where('category_id', $stage)->delete();

            $referenced = collect(self::REFERENCES)->contains(fn (string $table): bool => DB::table($table)->where('category_id', $stage)->exists());

            if (! $referenced) {
                DB::table('competition_categories')->where('id', $stage)->delete();
            }
        }

        $rounds = DB::table('competition_rounds')->join('competitions', 'competitions.id', '=', 'competition_rounds.competition_id')
            ->where('competitions.student_mode', true)->whereIn('competition_rounds.name', array_keys(self::ROUNDS))
            ->select('competition_rounds.id', 'competition_rounds.name')->get();

        foreach ($rounds as $round) {
            DB::table('competition_rounds')->where('id', $round->id)->update(['name' => self::ROUNDS[$round->name]]);
        }

        foreach (DB::table('competitions')->where('student_mode', true)->whereNotNull('page_content')->get(['id', 'page_content']) as $competition) {
            $content = json_decode((string) $competition->page_content, true);

            if (! is_array($content)) {
                continue;
            }

            $updated = self::rewrite($content);

            if ($updated !== $content) {
                DB::table('competitions')->where('id', $competition->id)->update(['page_content' => json_encode($updated, JSON_UNESCAPED_UNICODE)]);
            }
        }

        DB::table('competition_fields')->where('label', self::SCHOOL_FIELD)->update(['is_required' => true]);
    }

    /**
     * يُعاد ما يُعاد بلا الفئة المحذوفة ولا بند التحكيم: لا يُعرف أين كانا ولا يلزم رجوعهما.
     */
    public function down(): void
    {
        $rounds = DB::table('competition_rounds')->whereIn('name', array_values(self::ROUNDS))->get(['id', 'name']);

        foreach ($rounds as $round) {
            DB::table('competition_rounds')->where('id', $round->id)->update(['name' => array_search($round->name, self::ROUNDS, true)]);
        }

        DB::table('competition_fields')->where('label', self::SCHOOL_FIELD)->update(['is_required' => false]);
    }

    /**
     * @param  array<mixed>  $value
     * @return array<mixed>
     */
    private static function rewrite(array $value): array
    {
        $result = [];

        foreach ($value as $key => $item) {
            if ($item === self::JUDGING_RULE) {
                continue;
            }

            $result[$key] = match (true) {
                is_array($item) => self::rewrite($item),
                // ووسم الجملة المقطوعة في «عند وجود مشكلة» يُحذف، فتنتهي عند «من جديد.» (قرار صفوان، 29 أكتوبر 2026)
                is_string($item) => str_replace(['الفروع', ' (مثال تجريبي: آخر الجملة مقطوع في المصدر)'], ['المراحل', ''], $item),
                default => $item,
            };
        }

        // قائمة بلا مفاتيح (الضوابط مثلاً) تبقى قائمة بعد حذف البند
        return array_is_list($value) ? array_values($result) : $result;
    }
};
