<?php

namespace App\Filament\Resources\Committees\Pages;

use App\Actions\Registers\SaveCommittee;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Committees\CommitteeResource;
use App\Models\Committee;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateCommittee extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = CommitteeResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $record = new Committee;

        return $this->saveThroughAction($record, [], fn (): Committee => app(SaveCommittee::class)->handle($actor, $record, CommitteeResource::toAction($data)));
    }
}
