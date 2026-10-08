<?php

namespace App\Filament\Resources\ArchiveItems\Pages;

use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArchiveItems extends ListRecords
{
    protected static string $resource = ArchiveItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
