<?php

namespace App\Filament\Resources\PlatformAccounts\Pages;

use App\Filament\Resources\PlatformAccounts\PlatformAccountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAccounts extends ListRecords
{
    protected static string $resource = PlatformAccountResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
