<?php

namespace App\Filament\Resources\LiveStreams\Pages;

use App\Filament\Resources\LiveStreams\LiveStreamResource;
use App\Models\LiveStream;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditLiveStream extends EditRecord
{
    use SavesLiveStreamThroughAction;

    protected static string $resource = LiveStreamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LiveStreamResource::archiveAction(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var LiveStream $record */
        return $this->saveLiveStream($record, $data);
    }
}
