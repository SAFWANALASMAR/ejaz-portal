<?php

namespace App\Filament\Resources\ServiceRequests\Pages;

use App\Filament\Resources\ServiceRequests\ServiceRequestActions;
use App\Filament\Resources\ServiceRequests\ServiceRequestResource;
use App\Models\ServiceRequest;
use Filament\Resources\Pages\ViewRecord;

class ViewServiceRequest extends ViewRecord
{
    protected static string $resource = ServiceRequestResource::class;

    protected function getHeaderActions(): array
    {
        /** @var ServiceRequest $record */
        $record = $this->getRecord();
        $record->loadMissing(['events.actor']);

        return [
            ServiceRequestActions::changeStatus(),
            ServiceRequestActions::assign(),
            ...ServiceRequestActions::downloads($record),
        ];
    }
}
