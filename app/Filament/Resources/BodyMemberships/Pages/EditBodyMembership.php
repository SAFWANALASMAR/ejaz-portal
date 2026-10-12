<?php

namespace App\Filament\Resources\BodyMemberships\Pages;

use App\Actions\Registers\SaveBodyMembership;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\BodyMemberships\BodyMembershipResource;
use App\Models\BodyMembership;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditBodyMembership extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = BodyMembershipResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var BodyMembership $record */

        return $this->saveThroughAction($record, [], fn (): BodyMembership => app(SaveBodyMembership::class)->handle($actor, $record, BodyMembershipResource::toAction($data)));
    }
}
