<?php

namespace App\Filament\Resources\CompetitionRegions\Pages;

use App\Filament\Resources\CompetitionRegions\CompetitionRegionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompetitionRegions extends ManageRecords
{
    protected static string $resource = CompetitionRegionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
