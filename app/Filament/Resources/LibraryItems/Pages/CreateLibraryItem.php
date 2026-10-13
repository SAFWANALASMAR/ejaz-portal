<?php

namespace App\Filament\Resources\LibraryItems\Pages;

use App\Filament\Resources\LibraryItems\LibraryItemResource;
use App\Models\LibraryItem;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLibraryItem extends CreateRecord
{
    use SavesLibraryItemThroughAction;

    protected static string $resource = LibraryItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveLibraryItem(new LibraryItem, $data);
    }
}
