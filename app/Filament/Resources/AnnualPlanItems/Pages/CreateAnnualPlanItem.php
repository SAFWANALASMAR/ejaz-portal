<?php

namespace App\Filament\Resources\AnnualPlanItems\Pages;

use App\Actions\Registers\SaveAnnualPlanItem;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AnnualPlanItems\AnnualPlanItemResource;
use App\Models\AnnualPlanItem;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAnnualPlanItem extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AnnualPlanItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $record = new AnnualPlanItem;

        return $this->saveThroughAction($record, [], fn (): AnnualPlanItem => app(SaveAnnualPlanItem::class)->handle($actor, $record, AnnualPlanItemResource::toAction($data)));
    }
}
