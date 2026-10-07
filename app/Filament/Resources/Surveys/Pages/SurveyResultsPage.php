<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Enums\ActivityEvent;
use App\Enums\SurveyQuestionType;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\Survey;
use App\Models\SurveyResponse;
use App\Support\SurveyResults;
use Filament\Actions\Action;
use Filament\Resources\Pages\Concerns\InteractsWithRecord;
use Filament\Resources\Pages\Page;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * النتائج المجمعة لاستبيان لمن يطّلع عليه (ومنهم عضو المجلس)، بلا أسماء المستجيبين، وتصديرها CSV.
 */
class SurveyResultsPage extends Page
{
    use InteractsWithRecord;

    protected static string $resource = SurveyResource::class;

    protected string $view = 'filament.surveys.results';

    public function mount(int|string $record): void
    {
        $this->record = $this->resolveRecord($record);

        abort_unless(auth()->user()?->can('view', $this->getRecord()), 403);
    }

    public function getTitle(): string
    {
        return __('surveys.results.title', ['survey' => $this->survey()->title]);
    }

    /**
     * @return array{responses: int, questions: list<array<string, mixed>>}
     */
    public function getResults(): array
    {
        abort_unless(auth()->user()?->can('view', $this->survey()), 403);

        return SurveyResults::for($this->survey()->load('questions'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('export')
                ->label(__('surveys.results.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    /**
     * النتائج المجمعة ثم كل الإجابات النصية، بلا بيانات شخصية. تُبث صفاً صفاً فلا تُحمَّل كلها.
     */
    private function export(): StreamedResponse
    {
        $survey = $this->survey()->load('questions');
        abort_unless(auth()->user()?->can('view', $survey), 403);

        activity('surveys')->performedOn($survey)->causedBy(auth()->user())
            ->event(ActivityEvent::SurveyResultsExported->value)->log(ActivityEvent::SurveyResultsExported->value);

        return response()->streamDownload(function () use ($survey): void {
            $out = fopen('php://output', 'w');

            if ($out === false) {
                return;
            }

            // علامة الترميز ليقرأ Excel العربية
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, [__('surveys.results.csv_question'), __('surveys.results.csv_type'), __('surveys.results.csv_answer'), __('surveys.results.csv_count'), __('surveys.results.csv_average')], escape: '');

            foreach (SurveyResults::for($survey)['questions'] as $row) {
                $question = $row['question'];

                if ($question->type === SurveyQuestionType::Text) {
                    continue;
                }

                foreach ($row['counts'] ?? [] as $value => $count) {
                    $label = $question->type === SurveyQuestionType::Choice ? ($question->optionList()[$value] ?? '') : (string) $value;
                    fputcsv($out, self::safe([$question->prompt, $question->type->getLabel(), $label, $count, $row['average'] ?? '']), escape: '');
                }
            }

            foreach ($survey->questions->filter(fn ($question): bool => $question->type === SurveyQuestionType::Text) as $question) {
                $key = (string) $question->id;

                $survey->responses()->whereRaw('jsonb_exists(answers, ?)', [$key])->lazyById()->each(function (SurveyResponse $response) use ($out, $question, $key): void {
                    fputcsv($out, self::safe([$question->prompt, $question->type->getLabel(), (string) ($response->answers[$key] ?? ''), '', '']), escape: '');
                });
            }

            fclose($out);
        }, 'survey-'.$survey->uuid.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * النصوص المكتوبة من المستجيبين لا تُنفَّذ صيغاً في برامج الجداول.
     *
     * @param  list<mixed>  $row
     * @return list<mixed>
     */
    private static function safe(array $row): array
    {
        return array_map(fn ($value) => is_string($value) && preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value, $row);
    }

    private function survey(): Survey
    {
        /** @var Survey */
        return $this->getRecord();
    }
}
