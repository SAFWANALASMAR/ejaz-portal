<?php

namespace App\Filament\Resources\FormEntries\Pages;

use App\Actions\Documents\SaveFormEntry;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\FormEntries\FormEntryFields;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Models\FormEntry;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * تعديل المسودة بحقول إصدارها، لا الإصدار الحالي للنموذج.
 */
class EditFormEntry extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = FormEntryResource::class;

    public function form(Schema $schema): Schema
    {
        /** @var FormEntry $record */
        $record = $this->getRecord();

        return $schema->components([
            Section::make($record->version->template->title)->columnSpanFull()->schema(FormEntryFields::schema($record->version, $record)),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var FormEntry $record */
        $record = $this->getRecord();

        return FormEntryFields::fromEntry($record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var FormEntry $record */

        return $this->saveThroughAction($record, [], fn (): FormEntry => app(SaveFormEntry::class)->handle($actor, $record, FormEntryFields::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return FormEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
