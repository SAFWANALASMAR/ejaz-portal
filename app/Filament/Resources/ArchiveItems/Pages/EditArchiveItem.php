<?php

namespace App\Filament\Resources\ArchiveItems\Pages;

use App\Actions\Documents\SaveArchiveItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditArchiveItem extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = ArchiveItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var ArchiveItem $record */
        $record = $this->getRecord();

        return ArchiveItemResource::fromRecord($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var ArchiveItem $record */

        return $this->saveThroughAction($record, ['files'], fn (): ArchiveItem => app(SaveArchiveItem::class)->handle($actor, $record, ArchiveItemResource::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return ArchiveItemResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
