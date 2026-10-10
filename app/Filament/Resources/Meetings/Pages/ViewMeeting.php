<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Filament\Resources\Meetings\MeetingActions;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Models\Meeting;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMeeting extends ViewRecord
{
    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        /** @var Meeting $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),
            ...MeetingActions::all($record),
        ];
    }
}
