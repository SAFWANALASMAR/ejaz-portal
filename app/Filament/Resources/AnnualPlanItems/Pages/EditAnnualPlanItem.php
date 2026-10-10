<?php

namespace App\Filament\Resources\AnnualPlanItems\Pages;

use App\Actions\Registers\SaveAnnualPlanItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AnnualPlanItems\AnnualPlanItemResource;
use App\Models\AnnualPlanItem;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAnnualPlanItem extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AnnualPlanItemResource::class;

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
        /** @var AnnualPlanItem $record */

        return $this->saveThroughAction($record, [], fn (): AnnualPlanItem => app(SaveAnnualPlanItem::class)->handle($actor, $record, AnnualPlanItemResource::toAction($data)));
    }
}
