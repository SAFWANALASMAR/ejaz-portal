<?php

namespace App\Filament\Resources\RegisterEntries\Pages;

use App\Actions\Registers\SaveRegisterEntry;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Models\RegisterEntry;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRegisterEntry extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = RegisterEntryResource::class;

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
        /** @var RegisterEntry $record */
        $record = $this->getRecord();

        return RegisterEntryResource::fromRecord($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var RegisterEntry $record */

        return $this->saveThroughAction($record, ['attachments'], fn (): RegisterEntry => app(SaveRegisterEntry::class)->handle($actor, $record, RegisterEntryResource::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return RegisterEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
