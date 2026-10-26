<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Filament\Resources\Competitions\Actions\ShortLinkActions;
use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Schemas\CompetitionForm;
use App\Models\Competition;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompetition extends EditRecord
{
    use SavesCompetitionThroughAction;

    protected static string $resource = CompetitionResource::class;

    private function competitionRecord(): Competition
    {
        /** @var Competition */
        return $this->getRecord();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('stats')
                ->label(__('competitions.stats.action'))
                ->icon('heroicon-o-chart-bar')
                ->url(fn (): string => CompetitionResource::getUrl('stats', ['record' => $this->getRecord()])),
            ...ShortLinkActions::make(fn (): Competition => $this->competitionRecord()),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * المعايير والأسئلة بشكل النموذج.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Competition $competition */
        $competition = $this->getRecord();

        return [...$data, ...CompetitionForm::relationsState($competition)];
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
        /** @var Competition $record */
        return $this->saveCompetition($record, $data);
    }
}
