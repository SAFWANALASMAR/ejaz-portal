<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Enums\RankingScope;
use App\Models\CompetitionCategory;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Random\Randomizer;

/**
 * ترتيب اختبار الطالب العشوائي (قرار المدير التنفيذي: العشوائية إلزامية) من بنك فئته في المرحلة (أسئلتها وأسئلة
 * «كل الفئات»)، مجموعاً في وحدات: سؤال منفرد، أو نص أو مقطع بأسئلته مجتمعة لا تتفرق.
 *
 * البنك قد يزيد على عدد أسئلة المرحلة، فيُسحب لكل طالب عشوائياً من الوحدات ما مجموعه العدد تماماً (مراجعة
 * المشرف، 26 أكتوبر)، فيأخذ طلاب الفئة كلهم العدد نفسه. وحين يزيد البنك على العدد تتساوى درجات أسئلته، فتتساوى
 * الدرجة القصوى أيضاً. وما لا يكوّن العدد تماماً يُرفض البدء منه، وجدول المراحل يُظهره للمدير (shortfalls).
 * ثم تُخلط الوحدات، والأسئلة داخلها، وخياراتها (إلا صح وخطأ فترتيبه ثابت). والمولّد الآمن الافتراضي في
 * Randomizer، فلا يُتوقع الترتيب ولا الاختيار.
 */
final class AttemptLayout
{
    /**
     * @return list<array{passage: int|null, questions: list<array{id: int, options: list<string>}>}>
     */
    public static function build(CompetitionRound $round, int $categoryId, ?Randomizer $randomizer = null): array
    {
        $randomizer ??= new Randomizer;
        $units = self::pick($randomizer->shuffleArray(self::units(self::bank($round, $categoryId))), $round->question_count);

        if ($units === null) {
            return [];
        }

        return array_map(fn (array $unit): array => [
            'passage' => $unit[0]->passage_id,
            'questions' => array_map(fn (CompetitionQuestion $question): array => [
                'id' => $question->id,
                'options' => $question->type === QuestionType::TrueFalse
                    ? $question->optionKeys()
                    : $randomizer->shuffleArray($question->optionKeys()),
            ], $randomizer->shuffleArray($unit)),
        ], $randomizer->shuffleArray($units));
    }

    /**
     * بنك الفئة في المرحلة: أسئلتها وأسئلة «كل الفئات»، من الذاكرة المؤقتة بلا الإجابة الصحيحة.
     *
     * @return Collection<int, CompetitionQuestion>
     */
    public static function bank(CompetitionRound $round, int $categoryId): Collection
    {
        return RoundQuestions::load($round)['questions']
            ->filter(fn (CompetitionQuestion $question): bool => ($question->category_id === null || $question->category_id === $categoryId)
                && in_array($question->type, QuestionType::student(), true))
            ->values();
    }

    /**
     * الفئات المفتوحة التي لا يكوّن بنكها عدد أسئلة المرحلة تماماً، بأقصى عدد ممكن منه دون العدد: {الفئة: العدد}.
     * وبنك أكبر من العدد بدرجات أسئلة مختلفة غير جاهز أيضاً (عدده -1)، لأن الدرجة القصوى تختلف بين الطلاب.
     * والفارغة = جاهزة كلها.
     *
     * @return array<string, int>
     */
    public static function shortfalls(CompetitionRound $round): array
    {
        $short = [];

        foreach ($round->competition->openCategories()->get() as $category) {
            /** @var CompetitionCategory $category */
            $possible = self::shortfall($round, $category->id);

            if ($possible !== null) {
                $short[$category->name] = $possible;
            }
        }

        return $short;
    }

    /**
     * null إن كان بنك الفئة جاهزاً، وإلا أقصى عدد ممكن منه دون العدد، أو -1 لبنك أكبر بدرجات مختلفة.
     *
     * يُحسب مرة ويُخزَّن كـ mixedMaxScores، لا مع كل بدء اختبار (اختبار التحمل: كان كل بدء يبني البنك من الذاكرة
     * المؤقتة مرة ثانية ويعيد البرمجة الديناميكية): المفتاح بإصدار بنك المرحلة وعددها والفئة. والقيمة داخل مصفوفة،
     * لأن remember لا يخزّن null، وهو الجواب الغالب (جاهز). وبلا مدة كالبنك، لأن المفتاح يتغير مع كل تعديل، فلا
     * تنتهي مدته في نافذة أطول منها على كل الطلاب معاً (مراجعة 26 أكتوبر، ن١).
     */
    public static function shortfall(CompetitionRound $round, int $categoryId): ?int
    {
        /** @var array{0: int|null} $result */
        $result = Cache::rememberForever(
            'competition-round-shortfall:'.RoundQuestions::key($round->id).':'.$round->question_count.':'.$categoryId,
            function () use ($round, $categoryId): array {
                $bank = self::bank($round, $categoryId);
                $reachable = self::reachable(self::units($bank), $round->question_count);

                return [match (true) {
                    ! $reachable[0][$round->question_count] => (int) max(array_keys(array_filter($reachable[0]))),
                    $bank->count() > $round->question_count && $bank->pluck('points')->unique()->count() > 1 => -1,
                    default => null,
                }];
            },
        );

        return $result[0];
    }

    /**
     * الدرجة القصوى لطالب الفئة في المرحلة إن كان بنكها جاهزاً: مجموع درجات البنك إن ساوى العدد، وإلا العدد × درجة
     * السؤال الموحدة. أو null لبنك غير جاهز.
     */
    public static function maxScore(CompetitionRound $round, int $categoryId): ?float
    {
        if (self::shortfall($round, $categoryId) !== null) {
            return null;
        }

        $bank = self::bank($round, $categoryId);

        return $bank->count() === $round->question_count
            ? (float) $bank->sum('points')
            : (float) $round->question_count * (float) $bank->first()?->points;
    }

    /**
     * الدرجات القصوى المختلفة بين الفئات الجاهزة {الفئة: الدرجة} حين يكون الترتيب التراكمي لكل الفئات معاً (مراجعة
     * المشرف على المطوية): المجاميع تُقارن بين الفئات فيلزم أن تتوحّد الدرجة القصوى لكل فرع. وفارغة إن توحّدت، أو كان
     * الترتيب لكل فئة على حدة.
     *
     * يُحسب مرة ويُخزَّن (مراجعة المشرف على 0b788d7f)، لا مع كل بدء اختبار: المفتاح بإصدار بنك المرحلة (يتغير مع كل
     * تعديل فيه، ومع حفظ المسابقة لأن الفئات المفتوحة منها — SaveCompetition)، وبعدد أسئلتها.
     *
     * @return array<string, float>
     */
    public static function mixedMaxScores(CompetitionRound $round): array
    {
        if ($round->competition->ranking_scope === RankingScope::Category) {
            return [];
        }

        /** @var array<string, float> */
        return Cache::remember(
            'competition-round-mixed-max:'.RoundQuestions::key($round->id).':'.$round->question_count,
            now()->addMinutes((int) config('competitions.exam.cache_minutes')),
            function () use ($round): array {
                $scores = [];

                foreach ($round->competition->openCategories()->get() as $category) {
                    /** @var CompetitionCategory $category */
                    $max = self::maxScore($round, $category->id);

                    if ($max !== null) {
                        $scores[$category->name] = $max;
                    }
                }

                return count(array_unique($scores)) > 1 ? $scores : [];
            },
        );
    }

    /**
     * @param  Collection<int, CompetitionQuestion>  $bank
     * @return list<list<CompetitionQuestion>>
     */
    private static function units(Collection $bank): array
    {
        $units = [];

        foreach ($bank as $question) {
            $units[$question->passage_id !== null ? 'p'.$question->passage_id : 'q'.$question->id][] = $question;
        }

        return array_values($units);
    }

    /**
     * وحدات مجموع أسئلتها العدد تماماً، بترتيب الوحدات المعطى (العشوائي): تؤخذ الوحدة إن بقي العدد ممكناً بما بعدها.
     * أو null إن تعذّر.
     *
     * @param  list<list<CompetitionQuestion>>  $units
     * @return list<list<CompetitionQuestion>>|null
     */
    private static function pick(array $units, int $count): ?array
    {
        $reachable = self::reachable($units, $count);

        if (! $reachable[0][$count]) {
            return null;
        }

        $picked = [];
        $left = $count;

        foreach ($units as $index => $unit) {
            $size = count($unit);

            if ($size <= $left && $reachable[$index + 1][$left - $size]) {
                $picked[] = $unit;
                $left -= $size;
            }
        }

        return $picked;
    }

    /**
     * reachable[i][t]: هل تكوّن الوحدات من i إلى آخرها مجموعاً t تماماً (برمجة ديناميكية صغيرة: وحدات قليلة × عدد ≤ 200).
     *
     * @param  list<list<CompetitionQuestion>>  $units
     * @return array<int, array<int, bool>>
     */
    private static function reachable(array $units, int $count): array
    {
        $n = count($units);
        $reachable = [$n => array_fill(0, $count + 1, false)];
        $reachable[$n][0] = true;

        for ($i = $n - 1; $i >= 0; $i--) {
            $size = count($units[$i]);

            for ($t = 0; $t <= $count; $t++) {
                $reachable[$i][$t] = $reachable[$i + 1][$t] || ($t >= $size && $reachable[$i + 1][$t - $size]);
            }
        }

        return $reachable;
    }
}
