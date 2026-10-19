<?php

namespace App\Filament\Resources\AssistantGuides\Pages;

use App\Actions\Assistant\SaveAssistantGuide;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssistantGuides\AssistantGuideResource;
use App\Models\AssistantGuide;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssistantGuide extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AssistantGuideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var AssistantGuide $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, [], fn (): AssistantGuide => app(SaveAssistantGuide::class)->handle($actor, $record, $data));
    }
}
