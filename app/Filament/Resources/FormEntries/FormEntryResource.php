<?php

namespace App\Filament\Resources\FormEntries;

use App\Actions\Activity\LogAccess;
use App\Actions\Documents\ApproveFormEntry;
use App\Actions\Documents\PrintFormEntry;
use App\Enums\ActivityEvent;
use App\Enums\DocumentFieldType;
use App\Enums\FormEntryStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\FormEntries\Pages\CreateFormEntry;
use App\Filament\Resources\FormEntries\Pages\EditFormEntry;
use App\Filament\Resources\FormEntries\Pages\ListFormEntries;
use App\Filament\Resources\FormEntries\Pages\ViewFormEntry;
use App\Models\FormEntry;
use App\Models\FormTemplate;
use App\Models\User;
use App\Support\DocumentFields;
use App\Support\SignatureImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * النماذج المعبأة (form_entries.* — FormEntryPolicy): يعبّئها السكرتير ويعتمدها المجلس. والاطلاع على
 * المعبأ وتنزيل مرفقاته وطباعته تُسجَّل (requirements/05).
 */
class FormEntryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'documents.entries';

    protected static string $navigationGroupKey = 'archive';

    protected static ?string $model = FormEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        // ما عبّأه الحساب وحده، أو الكل لمن يملك form_entries.view_all (FormEntry::scopeVisibleInPanel)، فلا يظهر
        // معبأ غيره في القائمة ولا عددها ولا البحث، ولا يُفتح برابطه
        return parent::getEloquentQuery()->with(['version.template', 'creator', 'person'])->scopes(['visibleInPanel' => [self::actor()]]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('reference')->label(__('documents.fields.reference'))->copyable(),
                TextEntry::make('version.template.title')->label(__('documents.fields.template')),
                TextEntry::make('version.version')->label(__('documents.fields.version')),
                TextEntry::make('status')->label(__('documents.fields.status'))->badge(),
                TextEntry::make('creator.name')->label(__('documents.fields.created_by'))->placeholder('—'),
                TextEntry::make('person.name')->label(__('documents.fields.person'))->placeholder('—'),
                TextEntry::make('approver.name')->label(__('documents.fields.approved_by'))->placeholder('—'),
            ]),
            Section::make(__('documents.fields.answers'))->columns(2)->columnSpanFull()
                ->schema(fn (?FormEntry $record): array => $record === null ? [] : self::answerEntries($record)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('documents.fields.reference'))->searchable(),
                TextColumn::make('version.template.title')->label(__('documents.fields.template'))->wrap(),
                TextColumn::make('status')->label(__('documents.fields.status'))->badge(),
                TextColumn::make('person.name')->label(__('documents.fields.person'))->placeholder('—'),
                TextColumn::make('creator.name')->label(__('documents.fields.created_by'))->placeholder('—'),
                TextColumn::make('created_at')->label(__('documents.fields.created_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('documents.fields.status'))->options(FormEntryStatus::class),
                // ملف الشخص: معبآته كلها. والأسماء ممن في معبأ يطّلع عليه الحساب وحده
                SelectFilter::make('person')->label(__('documents.fields.person'))->relationship('person', 'name', fn (Builder $query) => $query->whereHas('formEntries', fn (Builder $entries) => $entries->scopes(['visibleInPanel' => [self::actor()]])))->searchable(),
                SelectFilter::make('template')
                    ->label(__('documents.fields.template'))
                    ->options(fn (): array => FormTemplate::withTrashed()->orderBy('title')->pluck('title', 'id')->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $id): Builder => $query->whereHas('version', fn (Builder $version) => $version->where('form_template_id', $id)),
                    )),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function approveAction(): Action
    {
        return Action::make('approve')
            ->label(__('documents.actions.approve'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('documents.actions.approve_confirm'))
            ->visible(fn (FormEntry $record): bool => self::actor()->can('approve', $record))
            ->action(function (FormEntry $record, ApproveFormEntry $approve): void {
                try {
                    $approve->handle(self::actor(), $record);
                } catch (AuthorizationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('documents.notices.approved'))->send();
            });
    }

    public static function printAction(): Action
    {
        return Action::make('print')
            ->label(__('documents.actions.print'))
            ->icon(Heroicon::OutlinedPrinter)
            ->color('gray')
            ->authorize(fn (FormEntry $record): bool => self::actor()->can('view', $record))
            ->action(function (FormEntry $record, PrintFormEntry $print): StreamedResponse {
                $pdf = $print->handle(self::actor(), $record);

                return response()->streamDownload(function () use ($pdf): void {
                    echo $pdf;
                }, $record->reference.'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    /**
     * المعبأ كورقة وورد في المتصفح، يُطبع منه (documents/form-entry-print).
     */
    public static function printViewAction(): Action
    {
        return Action::make('print_view')
            ->label(__('documents.actions.print_view'))
            ->icon(Heroicon::OutlinedDocumentText)
            ->color('gray')
            ->authorize(fn (FormEntry $record): bool => self::actor()->can('view', $record))
            ->url(fn (FormEntry $record): string => route('documents.entries.print', $record), shouldOpenInNewTab: true);
    }

    /**
     * زر تنزيل لكل مرفق، لمن يطّلع على المعبأ، ويُسجَّل.
     *
     * @return list<Action>
     */
    public static function downloads(FormEntry $entry): array
    {
        $actions = [];

        foreach ($entry->files ?? [] as $file) {
            $key = $file['key'];

            $actions[] = Action::make('download_'.$key)
                ->label(__('documents.actions.download', ['name' => $file['name']]))
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->authorize(fn (FormEntry $record): bool => self::actor()->can('view', $record))
                ->action(function (FormEntry $record, LogAccess $log) use ($key): StreamedResponse {
                    $file = collect($record->files ?? [])->firstWhere('key', $key) ?? abort(404);
                    $log->handle(self::actor(), $record, 'documents', ActivityEvent::FileDownloaded, ['file' => $key]);

                    return Storage::disk(FormEntry::disk())->download($file['path'], $file['name'], ['Content-Type' => 'application/pdf']);
                });
        }

        return $actions;
    }

    /**
     * @return list<Component>
     */
    private static function answerEntries(FormEntry $entry): array
    {
        $entries = [];

        foreach ($entry->version->typedFields() as $field) {
            $key = $field['key'];

            // عناوين الأقسام والنصوص الثابتة في عرض الطباعة، لا في الإجابات
            if ($field['type']->isLayout()) {
                continue;
            }

            $entries[] = match ($field['type']) {
                DocumentFieldType::Table => TextEntry::make('table_'.$key)
                    ->label($field['label'])
                    ->state(fn (): array => array_map(
                        fn (array $row): string => implode(' — ', array_map(fn (string $column): string => (string) ($row[$column] ?? ''), DocumentFields::columnKeys($field))),
                        (array) ($entry->answers[$key] ?? []),
                    ))
                    ->listWithLineBreaks()
                    ->placeholder('—')
                    ->columnSpanFull(),
                DocumentFieldType::Signature => ImageEntry::make('signature_'.$key)
                    ->label($field['label'])
                    ->state(fn (): ?string => ($signature = collect($entry->signatures ?? [])->firstWhere('key', $key)) === null ? null : SignatureImage::dataUrl(FormEntry::disk(), $signature['path']))
                    ->imageHeight(80),
                DocumentFieldType::File => TextEntry::make('file_'.$key)
                    ->label($field['label'])
                    ->state(fn (): ?string => collect($entry->files ?? [])->firstWhere('key', $key)['name'] ?? null)
                    ->placeholder('—'),
                default => TextEntry::make('answer_'.$key)
                    ->label($field['label'])
                    ->state(fn (): mixed => $entry->answers[$key] ?? null)
                    ->placeholder('—'),
            };
        }

        return $entries;
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFormEntries::route('/'),
            'create' => CreateFormEntry::route('/create'),
            'view' => ViewFormEntry::route('/{record}'),
            'edit' => EditFormEntry::route('/{record}/edit'),
        ];
    }
}
