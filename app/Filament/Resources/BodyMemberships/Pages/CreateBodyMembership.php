<?php

namespace App\Filament\Resources\BodyMemberships\Pages;

use App\Actions\Registers\SaveBodyMembership;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\BodyMemberships\BodyMembershipResource;
use App\Models\BodyMembership;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateBodyMembership extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = BodyMembershipResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $record = new BodyMembership;

        return $this->saveThroughAction($record, [], fn (): BodyMembership => app(SaveBodyMembership::class)->handle($actor, $record, BodyMembershipResource::toAction($data)));
    }
}
