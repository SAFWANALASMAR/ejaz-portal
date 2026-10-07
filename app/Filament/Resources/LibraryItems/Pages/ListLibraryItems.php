<?php

namespace App\Filament\Resources\LibraryItems\Pages;

use App\Filament\Resources\LibraryItems\LibraryItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLibraryItems extends ListRecords
{
    protected static string $resource = LibraryItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
