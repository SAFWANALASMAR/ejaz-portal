<?php

namespace App\Filament\Resources\Committees\Pages;

use App\Actions\Registers\SaveCommittee;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Committees\CommitteeResource;
use App\Models\Committee;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditCommittee extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = CommitteeResource::class;

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
        /** @var Committee $record */

        return $this->saveThroughAction($record, [], fn (): Committee => app(SaveCommittee::class)->handle($actor, $record, CommitteeResource::toAction($data)));
    }
}
