<?php

namespace App\Filament\Resources\Competitions\Pages;

use App\Filament\Resources\Competitions\CompetitionResource;
use App\Filament\Resources\Competitions\Schemas\CompetitionForm;
use App\Models\Competition;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * عرض المسابقة للقراءة لمن يطّلع (عضو المجلس ولجنة المسابقة)، ومعها سجل السحب.
 */
class ViewCompetition extends ViewRecord
{
    protected static string $resource = CompetitionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Competition $competition */
        $competition = $this->getRecord();

        return [...$data, ...CompetitionForm::relationsState($competition)];
    }
}
