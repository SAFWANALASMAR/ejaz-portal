<?php

namespace App\Filament\Resources\Partners\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\Partners\PartnerResource;
use App\Models\Partner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreatePartner extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = PartnerResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new Partner, $data);
    }
}
