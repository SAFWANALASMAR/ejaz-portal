<?php

namespace App\Filament\Resources\LibraryItems\Pages;

use App\Filament\Resources\LibraryItems\DownloadLibraryFileAction;
use App\Filament\Resources\LibraryItems\LibraryItemResource;
use App\Models\LibraryItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLibraryItem extends EditRecord
{
    use SavesLibraryItemThroughAction;

    protected static string $resource = LibraryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DownloadLibraryFileAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LibraryItem $record */
        return $this->saveLibraryItem($record, $data);
    }
}
