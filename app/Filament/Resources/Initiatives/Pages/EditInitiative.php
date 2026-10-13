<?php

namespace App\Filament\Resources\Initiatives\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Initiatives\InitiativeResource;
use App\Models\Initiative;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditInitiative extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = InitiativeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteContentAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Initiative $record */
        return $this->saveContent($record, $data);
    }
}
