<?php

namespace App\Filament\Resources\AnnualPlanItems\Pages;

use App\Filament\Resources\AnnualPlanItems\AnnualPlanItemResource;
use App\Filament\Widgets\AnnualPlanWidget;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnnualPlanItems extends ListRecords
{
    protected static string $resource = AnnualPlanItemResource::class;

    protected function getHeaderWidgets(): array
    {
        return [AnnualPlanWidget::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
