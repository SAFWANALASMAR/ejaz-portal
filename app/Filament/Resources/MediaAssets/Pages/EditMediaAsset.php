<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Actions\Content\SaveMediaAsset;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMediaAsset extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = MediaAssetResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var MediaAsset $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, [], fn (): MediaAsset => app(SaveMediaAsset::class)->handle($actor, $record, $data));
    }
}
