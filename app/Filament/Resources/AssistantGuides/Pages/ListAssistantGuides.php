<?php

namespace App\Filament\Resources\AssistantGuides\Pages;

use App\Filament\Resources\AssistantGuides\AssistantGuideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssistantGuides extends ListRecords
{
    protected static string $resource = AssistantGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
