<?php

namespace App\Filament\Resources\AssemblyMemberships\Pages;

use App\Actions\Assembly\SaveAssemblyMembership;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssemblyMemberships\AssemblyMembershipResource;
use App\Models\AssemblyMembership;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssemblyMembership extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AssemblyMembershipResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $membership = new AssemblyMembership;

        return $this->saveThroughAction($membership, [], fn (): AssemblyMembership => app(SaveAssemblyMembership::class)->handle($actor, $membership, $data));
    }
}
