<?php

namespace App\Filament\Resources\AssemblyMemberships\Pages;

use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssemblyMemberships extends ListRecords
{
    protected static string $resource = AssemblyMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
