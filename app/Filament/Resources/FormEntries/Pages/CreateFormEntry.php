<?php

namespace App\Filament\Resources\FormEntries\Pages;

use App\Actions\Documents\SaveFormEntry;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\FormEntries\FormEntryFields;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/**
 * تعبئة نموذج: اختيار نموذج مفعّل، ثم حقول إصداره الحالي.
 */
class CreateFormEntry extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = FormEntryResource::class;

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columnSpanFull()->schema([
                Select::make('form_template_id')
                    ->label(__('documents.fields.template'))
                    ->options(fn (): array => FormTemplate::query()->where('is_active', true)->whereNotNull('current_version_id')->orderBy('title')->pluck('title', 'id')->all())
                    ->required()
                    ->live(),
                Group::make()
                    ->schema(function (Get $get): array {
                        $version = FormTemplate::query()->find($get('form_template_id'))?->currentVersion;

                        return $version === null ? [] : FormEntryFields::schema($version);
                    })
                    ->columnSpanFull(),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $template = FormTemplate::query()->findOrFail($data['form_template_id']);
        $entry = new FormEntry;

        return $this->saveThroughAction($entry, [], fn (): FormEntry => app(SaveFormEntry::class)->handle($actor, $entry, FormEntryFields::toAction($data), $template));
    }

    protected function getRedirectUrl(): string
    {
        return FormEntryResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
