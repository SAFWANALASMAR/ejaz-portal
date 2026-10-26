<?php

namespace App\Filament\Resources\AccountCards\Pages;

use App\Filament\Resources\AccountCards\AccountCardResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAccountCards extends ListRecords
{
    protected static string $resource = AccountCardResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
