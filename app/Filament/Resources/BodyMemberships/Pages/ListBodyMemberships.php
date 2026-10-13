<?php

namespace App\Filament\Resources\BodyMemberships\Pages;

use App\Filament\Resources\BodyMemberships\BodyMembershipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListBodyMemberships extends ListRecords
{
    protected static string $resource = BodyMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
