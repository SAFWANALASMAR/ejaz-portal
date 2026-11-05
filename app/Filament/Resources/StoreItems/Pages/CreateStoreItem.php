<?php

namespace App\Filament\Resources\StoreItems\Pages;

use App\Actions\Store\SaveStoreItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\StoreItems\StoreItemResource;
use App\Models\StoreItem;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateStoreItem extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = StoreItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $item = new StoreItem;

        return $this->saveThroughAction($item, ['image'], fn (): StoreItem => app(SaveStoreItem::class)->handle($actor, $item, $data));
    }
}
