<?php

namespace App\Filament\Resources\RegisterEntries\Pages;

use App\Actions\Registers\SaveRegisterEntry;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Models\RegisterEntry;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRegisterEntry extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = RegisterEntryResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $entry = new RegisterEntry;

        return $this->saveThroughAction($entry, ['attachments'], fn (): RegisterEntry => app(SaveRegisterEntry::class)->handle($actor, $entry, RegisterEntryResource::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return RegisterEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
