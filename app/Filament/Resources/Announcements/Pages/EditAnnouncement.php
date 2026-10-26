<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAnnouncement extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteContentAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Announcement $record */
        return $this->saveContent($record, $data);
    }
}
