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

    /**
     * الصفحة لمن يطّلع على الاستبيانات، والسجل نفسه يُفحص في mount وعند كل قراءة وتصدير.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function canAccess(array $parameters = []): bool
    {
        return auth()->user()?->can('viewAny', Survey::class) ?? false;
    }

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
     * ومن لا يدير الاستبيان (عضو المجلس) يرى العدد وحده حتى خمس إجابات (SurveyResults::MIN_RESPONSES).
     *
     * @return array{responses: int, questions: list<array<string, mixed>>, withheld?: bool}
     */
    public function getResults(): array
    {
        abort_unless(auth()->user()?->can('view', $this->survey()), 403);

        $results = SurveyResults::for($this->survey()->load('questions'));

        // abort_unless أعلاه يضمن المستخدم
        if ($results['responses'] < SurveyResults::MIN_RESPONSES && ! auth()->user()->can('update', $this->survey())) {
            return SurveyResults::withheld($results);
        }

        return $results;
    }

    protected function getHeaderActions(): array
    {
        return [
            // التصدير بصلاحيته (surveys.export، لمسؤول البرامج)، لا بالاطلاع وحده: الملف يخرج من اللوحة
            Action::make('export')
                ->label(__('surveys.results.export'))
                ->icon('heroicon-o-arrow-down-tray')
                ->visible(fn (): bool => auth()->user()?->can('surveys.export') ?? false)
                ->action(fn (): StreamedResponse => $this->export()),
        ];
    }

    /**
     * النتائج المجمعة ثم كل الإجابات النصية، بلا بيانات شخصية. تُبث صفاً صفاً فلا تُحمَّل كلها.
     */
    private function export(): StreamedResponse
    {
        $survey = $this->survey()->load('questions');
        abort_unless(auth()->user()?->can('view', $survey) && auth()->user()->can('surveys.export'), 403);

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
        // ولو سبقتها فراغات (برامج الجداول تتجاهلها فتنفّذ الصيغة)
        return array_map(fn ($value) => is_string($value) && preg_match('/^[\s\x{00A0}\x{FEFF}]*[=+\-@\t\r]/u', $value) ? "'".$value : $value, $row);
    }

    private function survey(): Survey
    {
        /** @var Survey */
        return $this->getRecord();
    }
}
