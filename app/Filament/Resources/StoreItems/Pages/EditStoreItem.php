<?php

namespace App\Filament\Resources\StoreItems\Pages;

use App\Actions\Store\SaveStoreItem;
use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\StoreItems\StoreItemResource;
use App\Models\StoreItem;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditStoreItem extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = StoreItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteContentAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var StoreItem $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, ['image'], fn (): StoreItem => app(SaveStoreItem::class)->handle($actor, $record, $data));
    }
}
