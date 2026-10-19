<?php

namespace App\Filament\Resources\AssistantLogs\Pages;

use App\Filament\Resources\AssistantLogs\AssistantLogResource;
use Filament\Resources\Pages\ListRecords;

/**
 * المحادثات تصل من نافذة المساعد في الموقع، فلا زر إنشاء.
 */
class ListAssistantLogs extends ListRecords
{
    protected static string $resource = AssistantLogResource::class;
}
