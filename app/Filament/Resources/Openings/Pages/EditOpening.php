<?php

namespace App\Filament\Resources\Openings\Pages;

use App\Filament\Resources\Openings\OpeningResource;
use App\Models\Opening;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditOpening extends EditRecord
{
    use SavesOpeningThroughAction;

    protected static string $resource = OpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Opening $record */
        return $this->saveOpening($record, $data);
    }
}
