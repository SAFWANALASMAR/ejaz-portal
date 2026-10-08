<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Actions\Documents\SaveFormTemplate;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Models\FormTemplate;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateFormTemplate extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = FormTemplateResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $template = new FormTemplate;

        return $this->saveThroughAction($template, [], fn (): FormTemplate => app(SaveFormTemplate::class)->handle($actor, $template, $data));
    }
}
