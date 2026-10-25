<?php

namespace App\Support;

use App\Enums\QuestionType;
use App\Models\CompetitionCategory;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * ملف إكسل لبنك أسئلة مرحلة في مسابقة الطلاب: التصدير بالإجابات الصحيحة (لمن يدير المسابقات وحده)، والقالب
 * الفارغ للاستيراد بالأعمدة نفسها (ImportStudentQuestions). صف لكل سؤال، والخيارات حتى ستة، والإجابة الصحيحة
 * رقم الخيار (1–6) أو «صح» أو «خطأ».
 */
final class StudentQuestionsSheet
{
    public const OPTIONS = 6;

    /**
     * @return list<string>
     */
    public static function headings(): array
    {
        $options = [];

        for ($i = 1; $i <= self::OPTIONS; $i++) {
            $options[] = __('competitions.student.sheet.option', ['number' => $i]);
        }

        return [
            __('competitions.student.sheet.type'),
            __('competitions.student.sheet.category'),
            __('competitions.student.sheet.passage'),
            __('competitions.student.sheet.prompt'),
            ...$options,
            __('competitions.student.sheet.correct'),
            __('competitions.student.sheet.points'),
        ];
    }

    public static function download(CompetitionRound $round): StreamedResponse
    {
        $questions = $round->questions()->with('category')->get();

        return self::stream('questions-'.$round->uuid.'.xlsx', $questions->map(fn (CompetitionQuestion $question): array => self::row($question))->all());
    }

    public static function template(): StreamedResponse
    {
        return self::stream('questions-template.xlsx', []);
    }

    /**
     * @return list<string|int>
     */
    private static function row(CompetitionQuestion $question): array
    {
        $options = $question->type === QuestionType::TrueFalse ? [] : array_column($question->optionList(), 'label');
        $keys = array_column($question->optionList(), 'key');
        $correct = match (true) {
            $question->correct_option === null => '',
            $question->type === QuestionType::TrueFalse => (string) __('competitions.student.true_false.'.$question->correct_option),
            default => (string) ((int) array_search($question->correct_option, $keys, true) + 1),
        };

        return [
            $question->type->getLabel(),
            $question->category instanceof CompetitionCategory ? $question->category->name : '',
            $question->passage_id !== null ? (string) $question->passage_id : '',
            $question->prompt,
            ...array_pad($options, self::OPTIONS, ''),
            $correct,
            $question->points,
        ];
    }

    /**
     * @param  list<list<string|int>>  $rows
     */
    private static function stream(string $name, array $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows): void {
            $writer = new Writer;
            $writer->openToFile('php://output');
            $writer->addRow(Row::fromValues(self::headings()));

            foreach ($rows as $row) {
                $writer->addRow(Row::fromValues($row));
            }

            $writer->close();
        }, $name, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }
}
