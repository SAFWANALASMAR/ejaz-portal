<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Schemas\CompetitionForm;
use App\Models\Competition;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCompetition extends EditRecord
{
    use SavesCompetitionThroughAction;

    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Competition $record */
        return $this->saveCompetition($record, $data);
    }
}
