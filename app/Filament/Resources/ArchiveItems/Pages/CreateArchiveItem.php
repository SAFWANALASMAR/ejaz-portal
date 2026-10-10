<?php

namespace App\Filament\Resources\ArchiveItems\Pages;

use App\Actions\Documents\SaveArchiveItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateArchiveItem extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = ArchiveItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $item = new ArchiveItem;

        return $this->saveThroughAction($item, ['files'], fn (): ArchiveItem => app(SaveArchiveItem::class)->handle($actor, $item, ArchiveItemResource::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return ArchiveItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
