<?php

namespace App\Filament\Resources\Documents\Pages;

use App\Filament\Resources\Documents\DocumentResource;
use App\Filament\Resources\Documents\DownloadDocumentAction;
use App\Models\Document;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditDocument extends EditRecord
{
    use SavesDocumentThroughAction;

    protected static string $resource = DocumentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadDocumentAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Document $record */
        return $this->saveDocument($record, $data);
    }
}
