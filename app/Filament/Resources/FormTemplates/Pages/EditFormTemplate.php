<?php

namespace App\Filament\Resources\FormTemplates\Pages;

use App\Actions\Documents\SaveFormTemplate;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\FormTemplates\FormTemplateResource;
use App\Models\FormTemplate;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditFormTemplate extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = FormTemplateResource::class;

    public function getSubheading(): ?string
    {
        /** @var FormTemplate $record */
        $record = $this->getRecord();

        return __('documents.print.version', ['version' => $record->currentVersion->version ?? 0]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    /**
     * الحقول من الإصدار الحالي.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var FormTemplate $record */
        $record = $this->getRecord();

        return [...$data, 'fields' => $record->currentVersion->fields ?? []];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var FormTemplate $record */

        return $this->saveThroughAction($record, [], fn (): FormTemplate => app(SaveFormTemplate::class)->handle($actor, $record, $data));
    }
}
