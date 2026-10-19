<?php

namespace App\Filament\Resources\AssistantFaqs\Pages;

use App\Actions\Assistant\SaveAssistantFaq;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\AssistantFaqs\AssistantFaqResource;
use App\Models\AssistantFaq;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateAssistantFaq extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = AssistantFaqResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $faq = new AssistantFaq;

        return $this->saveThroughAction($faq, [], fn (): AssistantFaq => app(SaveAssistantFaq::class)->handle($actor, $faq, $data));
    }
}
