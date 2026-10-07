<?php

namespace App\Filament\Resources\Openings\Pages;

use App\Filament\Resources\Openings\OpeningResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpenings extends ListRecords
{
    protected static string $resource = OpeningResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
