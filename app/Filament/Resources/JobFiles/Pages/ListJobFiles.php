<?php

namespace App\Filament\Resources\JobFiles\Pages;

use App\Filament\Resources\JobFiles\JobFileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListJobFiles extends ListRecords
{
    protected static string $resource = JobFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
