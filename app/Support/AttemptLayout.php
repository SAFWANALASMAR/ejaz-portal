<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use Random\Randomizer;

/**
 * ترتيب اختبار الطالب العشوائي (قرار المدير التنفيذي: العشوائية إلزامية): أسئلة المرحلة لفئته ولكل الفئات،
 * مجموعة في وحدات (سؤال منفرد، أو نص أو مقطع بأسئلته مجتمعة)، تُخلط الوحدات ثم الأسئلة داخلها ثم خياراتها
 * (إلا صح وخطأ فترتيبه ثابت). وإن زاد البنك على العدد المطلوب أُخذت الوحدات بترتيبها العشوائي حتى يكتمل،
 * والوحدة التي لا تتسع تُتخطى. والمولّد الآمن الافتراضي في Randomizer، فلا يُتوقع الترتيب.
 */
final class AttemptLayout
{
    /**
     * @return list<array{passage: int|null, questions: list<array{id: int, options: list<string>}>}>
     */
    public static function build(CompetitionRound $round, int $categoryId, ?Randomizer $randomizer = null): array
    {
        $randomizer ??= new Randomizer;
        $questions = RoundQuestions::load($round)['questions']
            ->filter(fn (CompetitionQuestion $question): bool => ($question->category_id === null || $question->category_id === $categoryId)
                && in_array($question->type, QuestionType::student(), true));

        /** @var array<string, list<CompetitionQuestion>> $units */
        $units = [];

        foreach ($questions as $question) {
            $key = $question->passage_id !== null ? 'p'.$question->passage_id : 'q'.$question->id;
            $units[$key][] = $question;
        }

        $units = $randomizer->shuffleArray(array_values($units));
        $layout = [];
        $total = 0;
        $limit = max(1, $round->question_count);

        foreach ($units as $unit) {
            if ($total + count($unit) > $limit) {
                continue;
            }

            $layout[] = [
                'passage' => $unit[0]->passage_id,
                'questions' => array_map(fn (CompetitionQuestion $question): array => [
                    'id' => $question->id,
                    'options' => $question->type === QuestionType::TrueFalse
                        ? $question->optionKeys()
                        : $randomizer->shuffleArray($question->optionKeys()),
                ], $randomizer->shuffleArray($unit)),
            ];
            $total += count($unit);

            if ($total === $limit) {
                break;
            }
        }

        return $layout;
    }
}
