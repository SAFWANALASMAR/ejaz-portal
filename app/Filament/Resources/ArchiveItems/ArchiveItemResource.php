<?php

namespace App\Filament\Resources\ArchiveItems;

use App\Actions\Documents\LogDocumentAccess;
use App\Enums\ActivityEvent;
use App\Enums\ArchiveItemType;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\ArchiveItems\Pages\CreateArchiveItem;
use App\Filament\Resources\ArchiveItems\Pages\EditArchiveItem;
use App\Filament\Resources\ArchiveItems\Pages\ListArchiveItems;
use App\Filament\Resources\ArchiveItems\Pages\ViewArchiveItem;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\Program;
use App\Models\User;
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
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * الأرشيف الشامل والشواهد (archive.* — ArchiveItemPolicy). القائمة محصورة في التصنيفات التي يطّلع عليها
 * الحساب، والاطلاع على العنصر وتنزيل ملفاته يُسجَّلان. والحفظ عبر SaveArchiveItem.
 */
class ArchiveItemResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'documents.items';

    protected static string $navigationGroupKey = 'archive';

    protected static ?string $model = ArchiveItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static ?int $navigationSort = 3;

    /**
     * عناصر التصنيفات التي يطّلع عليها الحساب وحدها.
     *
     * @return Builder<Model>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()
            ->with(['category', 'program'])
            ->whereIn('archive_category_id', ArchiveCategory::visibleTo($user)->select('id'));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                Select::make('type')
                    ->label(__('documents.fields.type'))
                    ->options(ArchiveItemType::class)
                    ->required()
                    ->disabledOn('edit'),
                Select::make('archive_category_id')
                    ->label(__('documents.fields.category'))
                    ->options(function (): array {
                        /** @var User $user */
                        $user = auth()->user();

                        return ArchiveCategory::visibleTo($user)->orderBy('name')->pluck('name', 'id')->all();
                    })
                    ->required(),
                TextInput::make('title')->label(__('documents.fields.title'))->required()->maxLength(250)->columnSpanFull(),
                TextInput::make('entity')->label(__('documents.fields.entity'))->maxLength(200),
                DatePicker::make('dated_on')->label(__('documents.fields.dated_on'))->required(),
                Select::make('program_id')
                    ->label(__('documents.fields.program'))
                    ->options(fn (): array => Program::query()->orderByDesc('starts_at')->limit(200)->pluck('title', 'id')->all())
                    ->searchable(),
                TextInput::make('registration')
                    ->label(__('documents.fields.registration'))
                    ->helperText(__('documents.fields.registration_hint'))
                    ->disabled()
                    ->dehydrated(false)
                    ->formatStateUsing(fn (?ArchiveItem $record): ?string => $record?->registration()),
                Textarea::make('description')->label(__('documents.fields.description'))->rows(3)->maxLength(5000)->columnSpanFull(),
                FileUpload::make('files')
                    ->label(__('documents.fields.files'))
                    ->multiple()
                    ->maxFiles(20)
                    ->disk(ArchiveItem::disk())
                    ->directory(ArchiveItem::DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png', 'image/webp'])
                    ->maxSize(20480)
                    ->storeFileNamesIn('file_names')
                    ->previewable(false)
                    // المحفوظة مساراتٌ داخل [path, name]، فيُجاز منها ما يخص هذا العنصر وحده
                    ->preventFilePathTampering(allowFilePathUsing: fn (string $file, ?ArchiveItem $record): bool => $record !== null && in_array($file, array_column($record->files ?? [], 'path'), true))
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(3)->columnSpanFull()->schema([
                TextEntry::make('title')->label(__('documents.fields.title'))->columnSpanFull(),
                TextEntry::make('type')->label(__('documents.fields.type'))->badge(),
                TextEntry::make('category.name')->label(__('documents.fields.category')),
                TextEntry::make('registration')->label(__('documents.fields.registration'))->state(fn (ArchiveItem $record): ?string => $record->registration())->placeholder('—'),
                TextEntry::make('entity')->label(__('documents.fields.entity'))->placeholder('—'),
                TextEntry::make('dated_on')->label(__('documents.fields.dated_on'))->date('Y-m-d'),
                TextEntry::make('program.title')->label(__('documents.fields.program'))->placeholder('—'),
                TextEntry::make('description')->label(__('documents.fields.description'))->placeholder('—')->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('dated_on', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('documents.fields.title'))->searchable()->wrap(),
                TextColumn::make('type')->label(__('documents.fields.type'))->badge(),
                TextColumn::make('registration_number')
                    ->label(__('documents.fields.registration'))
                    ->formatStateUsing(fn (ArchiveItem $record): ?string => $record->registration())
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('entity')->label(__('documents.fields.entity'))->searchable()->toggleable(),
                TextColumn::make('category.name')->label(__('documents.fields.category')),
                TextColumn::make('dated_on')->label(__('documents.fields.dated_on'))->date('Y-m-d')->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->label(__('documents.fields.type'))->options(ArchiveItemType::class),
                SelectFilter::make('archive_category_id')
                    ->label(__('documents.fields.category'))
                    ->options(function (): array {
                        /** @var User $user */
                        $user = auth()->user();

                        return ArchiveCategory::visibleTo($user)->orderBy('name')->pluck('name', 'id')->all();
                    }),
                SelectFilter::make('year')
                    ->label(__('documents.fields.year'))
                    ->options(fn (): array => ArchiveItem::query()->distinct()->orderByDesc('year')->pluck('year', 'year')->all()),
                SelectFilter::make('program_id')->label(__('documents.fields.program'))->relationship('program', 'title')->searchable(),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    /**
     * زر تنزيل لكل ملف، لمن يطّلع على العنصر، ويُسجَّل.
     *
     * @return list<Action>
     */
    public static function downloads(ArchiveItem $item): array
    {
        $actions = [];

        foreach ($item->files ?? [] as $index => $file) {
            $actions[] = Action::make('download_'.$index)
                ->label(__('documents.actions.download', ['name' => $file['name']]))
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->authorize(fn (ArchiveItem $record): bool => self::actor()->can('view', $record))
                ->action(function (ArchiveItem $record, LogDocumentAccess $log) use ($index): StreamedResponse {
                    $file = ($record->files ?? [])[$index] ?? abort(404);
                    $log->handle(self::actor(), $record, ActivityEvent::FileDownloaded, ['file' => $file['name']]);

                    return Storage::disk(ArchiveItem::disk())->download($file['path'], $file['name']);
                });
        }

        return $actions;
    }

    /**
     * بيانات النموذج كما يأخذها SaveArchiveItem: الملفات مساراتٍ بأسمائها الأصلية.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        /** @var array<string, string> $names */
        $names = (array) ($data['file_names'] ?? []);
        $paths = array_values(array_filter((array) ($data['files'] ?? []), 'is_string'));

        unset($data['file_names']);
        $data['files'] = array_map(fn (string $path): array => ['path' => $path, 'name' => $names[$path] ?? basename($path)], $paths);

        return $data;
    }

    /**
     * والعكس عند فتح النموذج.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromRecord(ArchiveItem $item, array $data): array
    {
        $files = $item->files ?? [];

        return [...$data, 'files' => array_column($files, 'path'), 'file_names' => array_column($files, 'name', 'path')];
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }

    /**
     * والمحذوفة ناعماً تظهر بمرشّحها، لتُستعاد.
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
            'index' => ListArchiveItems::route('/'),
            'create' => CreateArchiveItem::route('/create'),
            'view' => ViewArchiveItem::route('/{record}'),
            'edit' => EditArchiveItem::route('/{record}/edit'),
        ];
    }
}
