<?php

namespace App\Filament\Resources\AssistantGuides\Pages;

use App\Actions\Assistant\SaveAssistantGuide;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssistantGuides\AssistantGuideResource;
use App\Models\AssistantGuide;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssistantGuide extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AssistantGuideResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $guide = new AssistantGuide;

        return $this->saveThroughAction($guide, [], fn (): AssistantGuide => app(SaveAssistantGuide::class)->handle($actor, $guide, $data));
    }
}
