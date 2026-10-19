<?php

namespace App\Filament\Resources\AssistantFaqs\Pages;

use App\Actions\Assistant\SaveAssistantFaq;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssistantFaqs\AssistantFaqResource;
use App\Models\AssistantFaq;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditAssistantFaq extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = AssistantFaqResource::class;

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
        /** @var AssistantFaq $record */
        /** @var User $actor */
        $actor = auth()->user();

        return $this->saveThroughAction($record, [], fn (): AssistantFaq => app(SaveAssistantFaq::class)->handle($actor, $record, $data));
    }
}
