<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Models\Announcement;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAnnouncement extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = AnnouncementResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new Announcement, $data);
    }
}
