<?php

namespace App\Support;

use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use App\Models\SurveyQuestion;

/**
 * النتائج المجمعة لاستبيان: لكل سؤال مقياس متوسطه وتوزيع درجاته الخمس، ولكل سؤال اختيار عدد كل خيار،
 * ولكل سؤال نص إجاباته (بلا أسماء). والتجميع في قاعدة البيانات على مفاتيح jsonb، فلا تُحمَّل كل
 * الإجابات للأسئلة غير النصية. والمفاتيح أرقام، فتُفحص بـ jsonb_exists لا بمسار JSON (الذي يعدّ الرقم موضعاً).
 */
final class SurveyResults
{
    public const TEXT_LIMIT = 200;

    /**
     * @return array{responses: int, questions: list<array{question: SurveyQuestion, answered: int, average?: float|null, counts?: array<int|string, int>, texts?: list<string>}>}
     */
    public static function for(Survey $survey): array
    {
        $results = [];

        foreach ($survey->questions as $question) {
            $key = (string) $question->id;
            $answers = $survey->responses()->toBase()->whereRaw('jsonb_exists(answers, ?)', [$key]);
            $answered = (clone $answers)->count();

            $row = ['question' => $question, 'answered' => $answered];

            if ($question->type === SurveyQuestionType::Text) {
                $row['texts'] = (clone $answers)->latest('submitted_at')->limit(self::TEXT_LIMIT)->pluck('answers')
                    ->map(fn ($answers): string => (string) (json_decode((string) $answers, true)[$key] ?? ''))
                    ->filter(fn (string $text): bool => $text !== '')
                    ->values()
                    ->all();
            } else {
                $counts = (clone $answers)
                    ->selectRaw('(answers->>?)::int as value, count(*) as total', [$key])
                    ->groupBy('value')
                    ->pluck('total', 'value');

                $scale = $question->type === SurveyQuestionType::Scale ? [1, 2, 3, 4, 5] : array_keys($question->optionList());
                $row['counts'] = collect($scale)->mapWithKeys(fn (int $value): array => [$value => (int) ($counts[$value] ?? 0)])->all();

                if ($question->type === SurveyQuestionType::Scale) {
                    $row['average'] = $answered === 0 ? null : round(collect($row['counts'])->map(fn (int $total, int $value): int => $total * $value)->sum() / $answered, 2);
                }
            }

            $results[] = $row;
        }

        return ['responses' => $survey->responses()->count(), 'questions' => $results];
    }
}
