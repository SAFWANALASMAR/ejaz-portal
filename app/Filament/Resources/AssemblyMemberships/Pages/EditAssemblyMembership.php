<?php

namespace App\Filament\Resources\AssemblyMemberships\Pages;

use App\Actions\Assembly\SaveAssemblyMembership;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Models\AssemblyMembership;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssemblyMembership extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AssemblyMembershipResource::class;

    public function getSubheading(): ?string
    {
        /** @var AssemblyMembership $record */
        $record = $this->getRecord();

        return $record->user->name;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var AssemblyMembership $record */

        return $this->saveThroughAction($record, [], fn (): AssemblyMembership => app(SaveAssemblyMembership::class)->handle($actor, $record, [
            'status' => $data['status'] ?? null,
            'joined_on' => $data['joined_on'] ?? null,
        ]));
    }
}
