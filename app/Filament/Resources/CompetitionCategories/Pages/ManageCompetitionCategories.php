<?php

namespace App\Filament\Resources\CompetitionCategories\Pages;

use App\Filament\Resources\CompetitionCategories\CompetitionCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageCompetitionCategories extends ManageRecords
{
    protected static string $resource = CompetitionCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
