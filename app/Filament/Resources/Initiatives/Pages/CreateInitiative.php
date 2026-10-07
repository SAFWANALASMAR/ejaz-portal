<?php

namespace App\Filament\Resources\Initiatives\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Initiatives\InitiativeResource;
use App\Models\Initiative;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateInitiative extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = InitiativeResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new Initiative, $data);
    }
}
