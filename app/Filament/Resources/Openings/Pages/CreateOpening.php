<?php

namespace App\Filament\Resources\Openings\Pages;

use App\Filament\Resources\Openings\OpeningResource;
use App\Models\Opening;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateOpening extends CreateRecord
{
    use SavesOpeningThroughAction;

    protected static string $resource = OpeningResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveOpening(new Opening, $data);
    }
}
