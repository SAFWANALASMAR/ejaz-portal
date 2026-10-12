<?php

namespace App\Filament\Resources\PlatformAccounts\Pages;

use App\Actions\Registers\SavePlatformAccount;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\PlatformAccounts\PlatformAccountResource;
use App\Models\PlatformAccount;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePlatformAccount extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = PlatformAccountResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $record = new PlatformAccount;

        return $this->saveThroughAction($record, [], fn (): PlatformAccount => app(SavePlatformAccount::class)->handle($actor, $record, PlatformAccountResource::toAction($data)));
    }
}
