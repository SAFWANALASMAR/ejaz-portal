<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Exports\ServiceRequestExporter;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Actions\ExportAction;
use Filament\Resources\Pages\ListRecords;

class ListServiceRequests extends ListRecords
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ExportAction::make()
                ->label(__('services.admin.actions.export'))
                ->exporter(ServiceRequestExporter::class)
                ->authorize(fn (): bool => auth()->user()?->can('export', ServiceRequest::class) ?? false),
        ];
    }
}
