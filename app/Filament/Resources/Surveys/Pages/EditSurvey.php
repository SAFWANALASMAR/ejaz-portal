<?php

namespace App\Filament\Resources\Surveys\Pages;

use App\Filament\Resources\Surveys\Schemas\SurveyForm;
use App\Filament\Resources\Surveys\SurveyResource;
use App\Models\Survey;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSurvey extends EditRecord
{
    use SavesSurveyThroughAction;

    protected static string $resource = SurveyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('results')
                ->label(__('surveys.results.action'))
                ->icon('heroicon-o-chart-pie')
                ->url(fn (): string => SurveyResource::getUrl('results', ['record' => $this->getRecord()])),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Survey $survey */
        $survey = $this->getRecord();

        return [...$data, ...SurveyForm::relationsState($survey)];
    }

    /**
     * المعرّفات الجديدة إلى النموذج بعد الحفظ، فلا يُعاد إنشاء ما أُضيف إن حُفظت الصفحة ثانية.
     */
    protected function afterSave(): void
    {
        $this->fillForm();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Survey $record */
        return $this->saveSurvey($record, $data);
    }
}
