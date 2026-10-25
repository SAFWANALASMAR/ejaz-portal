<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\CompetitionCategory;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Illuminate\Database\Eloquent\Collection;
use Random\Randomizer;

/**
 * ترتيب اختبار الطالب العشوائي (قرار المدير التنفيذي: العشوائية إلزامية): أسئلة المرحلة لفئته ولكل الفئات كلها،
 * مجموعة في وحدات (سؤال منفرد، أو نص أو مقطع بأسئلته مجتمعة)، تُخلط الوحدات ثم الأسئلة داخلها ثم خياراتها (إلا
 * صح وخطأ فترتيبه ثابت). وبنك كل فئة بعدد أسئلة المرحلة تماماً (مراجعة المشرف)، فيُسأل الطلاب كلهم الأسئلة
 * نفسها بالعدد والدرجة القصوى نفسيهما، ولا يبدأ أحد الاختبار من بنك ناقص أو زائد (StartAttempt). والمولّد الآمن
 * الافتراضي في Randomizer، فلا يُتوقع الترتيب.
 */
final class AttemptLayout
{
    /**
     * @return list<array{passage: int|null, questions: list<array{id: int, options: list<string>}>}>
     */
    public static function build(CompetitionRound $round, int $categoryId, ?Randomizer $randomizer = null): array
    {
        $randomizer ??= new Randomizer;

        /** @var array<string, list<CompetitionQuestion>> $units */
        $units = [];

        foreach (self::bank($round, $categoryId) as $question) {
            $key = $question->passage_id !== null ? 'p'.$question->passage_id : 'q'.$question->id;
            $units[$key][] = $question;
        }

        return array_map(fn (array $unit): array => [
            'passage' => $unit[0]->passage_id,
            'questions' => array_map(fn (CompetitionQuestion $question): array => [
                'id' => $question->id,
                'options' => $question->type === QuestionType::TrueFalse
                    ? $question->optionKeys()
                    : $randomizer->shuffleArray($question->optionKeys()),
            ], $randomizer->shuffleArray($unit)),
        ], $randomizer->shuffleArray(array_values($units)));
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
     * الفئات المفتوحة التي لا يساوي بنكها عدد أسئلة المرحلة، بعدد أسئلتها: {اسم الفئة: العدد}. فارغة = جاهزة.
     *
     * @return array<string, int>
     */
    public static function shortfalls(CompetitionRound $round): array
    {
        return $round->competition->openCategories()->get()
            ->mapWithKeys(fn (CompetitionCategory $category): array => [$category->name => self::bank($round, $category->id)->count()])
            ->reject(fn (int $count): bool => $count === $round->question_count)
            ->all();
    }
}
