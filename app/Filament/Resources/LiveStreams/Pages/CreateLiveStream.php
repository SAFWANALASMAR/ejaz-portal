<?php

namespace App\Filament\Resources\LiveStreams\Pages;

use App\Filament\Resources\LiveStreams\LiveStreamResource;
use App\Models\LiveStream;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateLiveStream extends CreateRecord
{
    use SavesLiveStreamThroughAction;

    protected static string $resource = LiveStreamResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveLiveStream(new LiveStream, $data);
    }
}
