<?php

namespace App\Filament\Resources\AssistantFaqs\Pages;

use App\Filament\Resources\AssistantFaqs\AssistantFaqResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssistantFaqs extends ListRecords
{
    protected static string $resource = AssistantFaqResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
