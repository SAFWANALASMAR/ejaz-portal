<?php

namespace App\Support;

use App\Enums\SurveyQuestionType;
use App\Models\Survey;
use App\Models\SurveyQuestion;
use Illuminate\Support\Facades\Cache;

/**
 * النتائج المجمعة لاستبيان: لكل سؤال مقياس متوسطه وتوزيع درجاته الخمس، ولكل سؤال اختيار عدد كل خيار،
 * ولكل سؤال نص إجاباته (بلا أسماء). والتجميع في قاعدة البيانات على مفاتيح jsonb، فلا تُحمَّل كل
 * الإجابات للأسئلة غير النصية. والمفاتيح أرقام، فتُفحص بـ jsonb_exists لا بمسار JSON (الذي يعدّ الرقم موضعاً).
 */
final class SurveyResults
{
    public const TEXT_LIMIT = 200;

    /**
     * أقل عدد إجابات تظهر بعده التوزيعات والنصوص لمن لا يدير الاستبيان، فلا يُعرف المستجيب في
     * استبيان صغير من إجابته.
     */
    public const MIN_RESPONSES = 5;

    /**
     * النتائج بالعدد الكلي وحده، بلا تفاصيل الأسئلة، وعلامة الحجب.
     *
     * @param  array{responses: int, questions: list<array<string, mixed>>}  $results
     * @return array{responses: int, questions: list<array<string, mixed>>, withheld: bool}
     */
    public static function withheld(array $results): array
    {
        return ['responses' => $results['responses'], 'questions' => [], 'withheld' => true];
    }

    /**
     * @return array{responses: int, questions: list<array{question: SurveyQuestion, answered: int, average?: float|null, counts?: array<int|string, int>, texts?: list<string>}>}
     */
    public static function for(Survey $survey): array
    {
        // تُخزَّن خمس دقائق، فلا تُحسب عند كل عرض أو تصدير، وتُمسح بكل إجابة جديدة (SubmitSurveyResponse).
        // والذاكرة تحفظ مصفوفات لا كائنات (decisions.md)، فيُخزَّن معرّف السؤال ويُعاد ربطه بعد القراءة
        $stored = Cache::remember(self::key($survey), 300, function () use ($survey): array {
            $results = self::compute($survey);
            $results['questions'] = array_map(fn (array $row): array => ['question' => $row['question']->id, ...array_diff_key($row, ['question' => true])], $results['questions']);

            return $results;
        });

        $questions = $survey->questions->keyBy('id');

        $stored['questions'] = array_values(array_filter(array_map(
            fn (array $row): ?array => $questions->has($row['question']) ? ['question' => $questions[$row['question']], ...array_diff_key($row, ['question' => true])] : null,
            $stored['questions'],
        )));

        return $stored;
    }

    public static function forget(Survey $survey): void
    {
        Cache::forget(self::key($survey));
    }

    private static function key(Survey $survey): string
    {
        return 'surveys.results.'.$survey->id;
    }

    /**
     * @return array{responses: int, questions: list<array{question: SurveyQuestion, answered: int, average?: float|null, counts?: array<int|string, int>, texts?: list<string>}>}
     */
    private static function compute(Survey $survey): array
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
