<?php

namespace App\Filament\Resources\FormEntries\Pages;

use App\Filament\Resources\FormEntries\FormEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFormEntries extends ListRecords
{
    protected static string $resource = FormEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label(__('documents.actions.fill')),
        ];
    }
}
