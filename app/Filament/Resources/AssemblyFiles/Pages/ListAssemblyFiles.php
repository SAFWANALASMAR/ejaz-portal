<?php

namespace App\Filament\Resources\AssemblyFiles\Pages;

use App\Filament\Resources\AssemblyFiles\AssemblyFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyFiles extends ListRecords
{
    protected static string $resource = AssemblyFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
