<?php

namespace App\Filament\Resources\FormTemplates;

use App\Actions\Documents\ExportFormEntries;
use App\Enums\FormAudience;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Forms\DocumentFieldsRepeater;
use App\Filament\Resources\FormTemplates\Pages\CreateFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\EditFormTemplate;
use App\Filament\Resources\FormTemplates\Pages\ListFormTemplates;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * النماذج الإلكترونية (forms.* — FormTemplatePolicy): يبنيها السكرتير ويطّلع عليها المجلس. والحذف ناعم.
 */
class FormTemplateResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'documents.templates';

    protected static string $navigationGroupKey = 'archive';

    protected static ?string $model = FormTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentDuplicate;

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('currentVersion');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('documents.fields.title'))->required()->maxLength(200),
                Toggle::make('is_active')->label(__('documents.fields.is_active'))->default(true)->inline(false),
                Select::make('audience')
                    ->label(__('documents.fields.audience'))
                    ->helperText(__('documents.fields.audience_hint'))
                    ->options(FormAudience::class)
                    ->default(FormAudience::Staff)
                    ->selectablePlaceholder(false)
                    ->required(),
                Textarea::make('description')->label(__('documents.fields.description'))->rows(2)->maxLength(1000)->columnSpanFull(),
                DocumentFieldsRepeater::make(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('documents.fields.title'))->searchable()->wrap(),
                TextColumn::make('currentVersion.version')->label(__('documents.fields.version')),
                TextColumn::make('audience')->label(__('documents.fields.audience'))->badge(),
                IconColumn::make('is_active')->label(__('documents.fields.is_active'))->boolean(),
                TextColumn::make('updated_at')->label(__('documents.fields.created_at'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                // معبأ النموذج CSV لمن له form_entries.export، ويُسجَّل (ExportFormEntries)
                Action::make('export')
                    ->label(__('documents.actions.export'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->color('gray')
                    ->visible(fn (): bool => auth()->user()?->can('export', FormEntry::class) ?? false)
                    ->action(function (FormTemplate $record, ExportFormEntries $export): StreamedResponse {
                        /** @var User $actor */
                        $actor = auth()->user();

                        return $export->handle($actor, $record);
                    }),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormTemplates::route('/'),
            'create' => CreateFormTemplate::route('/create'),
            'edit' => EditFormTemplate::route('/{record}/edit'),
        ];
    }
}
