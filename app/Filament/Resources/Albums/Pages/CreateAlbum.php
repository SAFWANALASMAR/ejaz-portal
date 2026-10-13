<?php

namespace App\Filament\Resources\Albums\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Albums\AlbumResource;
use App\Models\Album;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAlbum extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = AlbumResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new Album, $data);
    }
}
