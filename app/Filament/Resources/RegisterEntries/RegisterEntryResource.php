<?php

namespace App\Filament\Resources\RegisterEntries;

use App\Enums\RegisterEntryStatus;
use App\Enums\RegisterEntryType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\RegisterEntries\Pages\CreateRegisterEntry;
use App\Filament\Resources\RegisterEntries\Pages\EditRegisterEntry;
use App\Filament\Resources\RegisterEntries\Pages\ListRegisterEntries;
use App\Filament\Resources\RegisterEntries\Pages\ViewRegisterEntry;
use App\Models\RegisterEntry;
use App\Models\User;
use App\Support\Registers;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * السجلات والمستندات ذات الصلاحية (requirements/13 §1). يديرها المدير التنفيذي والسكرتير، ويطّلع عليها
 * المجلس (registers.* — RegisterEntryPolicy). المتبقي والحالة محسوبان، والتصفية بالنوع والحالة والمسؤول،
 * والمرفقات تُنزَّل برابط مؤقت موقّع، وفتح صفحة المستند يُسجَّل.
 */
class RegisterEntryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'registers.entry';

    protected static string $navigationGroupKey = 'registers';

    protected static ?string $model = RegisterEntry::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?int $navigationSort = 1;

    /**
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('responsible');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('type')->label(__('registers.fields.type'))->options(RegisterEntryType::class)->required(),
                TextInput::make('document_number')->label(__('registers.fields.document_number'))->maxLength(100),
                TextInput::make('title')->label(__('registers.fields.title'))->required()->maxLength(250)->columnSpanFull(),
                TextInput::make('issuer')->label(__('registers.fields.issuer'))->maxLength(200),
                Select::make('responsible_id')
                    ->label(__('registers.fields.responsible'))
                    ->options(fn (): array => Registers::usersWhoCan('registers.view')->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                DatePicker::make('issued_on')->label(__('registers.fields.issued_on'))->required(),
                DatePicker::make('expires_on')->label(__('registers.fields.expires_on'))->helperText(__('registers.fields.expires_on_hint'))->afterOrEqual('issued_on'),
                TextInput::make('alert_days')
                    ->label(__('registers.fields.alert_days'))
                    ->integer()
                    ->minValue(0)
                    ->maxValue(730)
                    ->required()
                    ->default(fn (): int => Registers::defaultAlertDays()),
                Textarea::make('notes')->label(__('registers.fields.notes'))->rows(3)->maxLength(5000)->columnSpanFull(),
                FileUpload::make('attachments')
                    ->label(__('registers.fields.attachments'))
                    ->multiple()
                    ->maxFiles(10)
                    ->disk(RegisterEntry::disk())
                    ->directory(RegisterEntry::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('attachment_names')
                    // لا رابط معاينة: المرفق يُنزَّل برابط موقّع من صفحة المستند بعد فحص الصلاحية
                    ->previewable(false)
                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?RegisterEntry $record): bool => $record !== null && in_array($file, array_column($record->attachments ?? [], 'path'), true))
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('title')->label(__('registers.fields.title'))->columnSpanFull(),
                TextEntry::make('type')->label(__('registers.fields.type'))->badge(),
                TextEntry::make('status')->label(__('registers.fields.status'))->badge()->state(fn (RegisterEntry $record): RegisterEntryStatus => $record->status()),
                TextEntry::make('remaining')->label(__('registers.fields.remaining_days'))->state(fn (RegisterEntry $record): ?string => self::remaining($record))->placeholder('—'),
                TextEntry::make('issuer')->label(__('registers.fields.issuer'))->placeholder('—'),
                TextEntry::make('document_number')->label(__('registers.fields.document_number'))->placeholder('—'),
                TextEntry::make('responsible.name')->label(__('registers.fields.responsible'))->placeholder('—'),
                TextEntry::make('issued_on')->label(__('registers.fields.issued_on'))->date('Y-m-d'),
                TextEntry::make('expires_on')->label(__('registers.fields.expires_on'))->date('Y-m-d')->placeholder('—'),
                TextEntry::make('alert_days')->label(__('registers.fields.alert_days')),
                TextEntry::make('notes')->label(__('registers.fields.notes'))->placeholder('—')->columnSpanFull()->extraAttributes(['class' => 'whitespace-pre-line']),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('expires_on')
            ->columns([
                TextColumn::make('title')->label(__('registers.fields.title'))->searchable()->wrap(),
                TextColumn::make('type')->label(__('registers.fields.type'))->badge(),
                TextColumn::make('issuer')->label(__('registers.fields.issuer'))->searchable()->toggleable(),
                TextColumn::make('expires_on')->label(__('registers.fields.expires_on'))->date('Y-m-d')->sortable()->placeholder('—'),
                TextColumn::make('remaining')->label(__('registers.fields.remaining_days'))->state(fn (RegisterEntry $record): ?string => self::remaining($record))->placeholder('—'),
                TextColumn::make('status')->label(__('registers.fields.status'))->badge()->state(fn (RegisterEntry $record): RegisterEntryStatus => $record->status()),
                TextColumn::make('responsible.name')->label(__('registers.fields.responsible'))->placeholder('—')->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('registers.fields.type'))->options(RegisterEntryType::class),
                SelectFilter::make('status')
                    ->label(__('registers.fields.status'))
                    ->options(RegisterEntryStatus::class)
                    ->query(function (Builder $query, array $data): void {
                        $status = RegisterEntryStatus::tryFrom((string) ($data['value'] ?? ''));

                        if ($status !== null) {
                            /** @var Builder<RegisterEntry> $query */
                            $query->whereStatus($status);
                        }
                    }),
                SelectFilter::make('responsible_id')
                    ->label(__('registers.fields.responsible'))
                    ->options(fn (): array => Registers::usersWhoCan('registers.view')->orderBy('name')->pluck('name', 'id')->all()),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function remaining(RegisterEntry $entry): ?string
    {
        $days = $entry->remainingDays();

        return $days === null ? null : number_format($days);
    }

    /**
     * تنزيل كل مرفق برابط مؤقت موقّع، لمن يطّلع على المستند. ويُسجَّل التنزيل عند الطلب.
     *
     * @return list<Action>
     */
    public static function downloads(RegisterEntry $entry): array
    {
        $actions = [];

        foreach ($entry->attachments ?? [] as $index => $file) {
            $actions[] = Action::make('download_'.$index)
                ->label(__('registers.actions.download', ['name' => $file['name']]))
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->visible(fn (RegisterEntry $record): bool => ! $record->trashed())
                ->authorize(fn (RegisterEntry $record): bool => self::actor()->can('view', $record))
                ->url(fn (RegisterEntry $record): string => $record->attachmentUrl($index));
        }

        return $actions;
    }

    /**
     * بيانات النموذج كما يأخذها SaveRegisterEntry: المرفقات مساراتٍ بأسمائها الأصلية.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        /** @var array<string, string> $names */
        $names = (array) ($data['attachment_names'] ?? []);
        $paths = array_values(array_filter((array) ($data['attachments'] ?? []), 'is_string'));

        unset($data['attachment_names']);
        $data['type'] = $data['type'] instanceof RegisterEntryType ? $data['type']->value : ($data['type'] ?? null);
        $data['attachments'] = array_map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)], $paths);

        return $data;
    }

    /**
     * والعكس عند فتح النموذج.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromRecord(RegisterEntry $entry, array $data): array
    {
        $files = $entry->attachments ?? [];

        return [...$data, 'attachments' => array_column($files, 'path'), 'attachment_names' => array_column($files, 'name', 'path')];
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    /**
     * المحذوفة حذفاً ناعماً تظهر بمرشّحها، لتُستعاد.
     *
     * @return Builder<Model>
     */
    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRegisterEntries::route('/'),
            'create' => CreateRegisterEntry::route('/create'),
            'view' => ViewRegisterEntry::route('/{record}'),
            'edit' => EditRegisterEntry::route('/{record}/edit'),
        ];
    }
}
