<?php

namespace App\Filament\Resources\EvidenceGalleries;

use App\Enums\ArchiveItemType;
use App\Enums\EvidenceLinkStatus;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\EvidenceGalleries\Pages\CreateEvidenceGallery;
use App\Filament\Resources\EvidenceGalleries\Pages\EditEvidenceGallery;
use App\Filament\Resources\EvidenceGalleries\Pages\ListEvidenceGalleries;
use App\Filament\Resources\EvidenceGalleries\RelationManagers\VisitsRelationManager;
use App\Models\Album;
use App\Models\ArchiveCategory;
use App\Models\ArchiveItem;
use App\Models\EvidenceGallery;
use App\Models\Program;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * معارض الشواهد برابط سري (evidence_galleries.* — EvidenceGalleryPolicy): العنوان والوصف والبرنامج والفترة، والبنود
 * ألبومات وشواهد موجودة في الأرشيف بترتيبها. والشواهد من التصنيفات التي يطّلع عليها الحساب وحدها، والألبومات
 * المنشورة وحدها. والحفظ عبر SaveEvidenceGallery، والرابط من صفحة التعديل (IssueEvidenceLink وRevokeEvidenceLink).
 */
class EvidenceGalleryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'evidence';

    protected static string $navigationGroupKey = 'archive';

    protected static ?string $model = EvidenceGallery::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(2)->columnSpanFull()->schema([
                TextInput::make('title')->label(__('evidence.fields.title'))->required()->maxLength(200)->columnSpanFull(),
                Textarea::make('description')->label(__('evidence.fields.description'))->rows(3)->maxLength(5000)->columnSpanFull(),
                Select::make('program_id')
                    ->label(__('evidence.fields.program'))
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Program::query()->where('title', 'ilike', self::like($search))->latest('id')->limit(50)->pluck('title', 'id')->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Program::withTrashed()->whereKey($value)->value('title'))
                    ->columnSpanFull(),
                DatePicker::make('period_from')->label(__('evidence.fields.period_from')),
                DatePicker::make('period_to')->label(__('evidence.fields.period_to'))->afterOrEqual('period_from'),
            ]),
            Section::make(__('evidence.fields.items'))->description(__('evidence.fields.items_hint'))->columnSpanFull()->schema([
                Repeater::make('items')
                    ->hiddenLabel()
                    ->addActionLabel(__('evidence.actions.add_item'))
                    ->defaultItems(0)
                    ->maxItems(EvidenceGallery::MAX_ITEMS)
                    ->reorderable()
                    ->collapsible()
                    ->columns(2)
                    ->itemLabel(fn (array $state): ?string => self::itemLabel($state))
                    ->schema([
                        Select::make('source')
                            ->label(__('evidence.fields.source'))
                            ->options(['album' => __('evidence.sources.album'), 'archive' => __('evidence.sources.archive')])
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('paths', []))
                            ->columnSpanFull(),
                        Select::make('album_id')
                            ->label(__('evidence.fields.album'))
                            ->searchable()
                            ->options(fn (): array => self::albums()->newestFirst()->limit(20)->pluck('title', 'id')->all())
                            ->getSearchResultsUsing(fn (string $search): array => self::albums()->where('title', 'ilike', self::like($search))->newestFirst()->limit(50)->pluck('title', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => self::albumTitle($value))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('paths', []))
                            ->visible(fn (Get $get): bool => $get('source') === 'album'),
                        Select::make('archive_item_id')
                            ->label(__('evidence.fields.archive_item'))
                            ->searchable()
                            ->options(fn (): array => self::evidence()->orderByDesc('dated_on')->limit(20)->pluck('title', 'id')->all())
                            ->getSearchResultsUsing(fn (string $search): array => self::evidence()->where('title', 'ilike', self::like($search))->orderByDesc('dated_on')->limit(50)->pluck('title', 'id')->all())
                            ->getOptionLabelUsing(fn ($value): ?string => self::evidenceTitle($value))
                            ->required()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('paths', []))
                            ->visible(fn (Get $get): bool => $get('source') === 'archive'),
                        Select::make('paths')
                            ->label(fn (Get $get): string => $get('source') === 'album' ? __('evidence.fields.photos') : __('evidence.fields.files'))
                            ->helperText(__('evidence.fields.selection_hint'))
                            ->multiple()
                            ->options(fn (Get $get): array => self::sourceOptions($get('source'), $get('album_id'), $get('archive_item_id')))
                            ->visible(fn (Get $get): bool => filled($get('album_id')) || filled($get('archive_item_id'))),
                    ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // المُصدر مع الصفوف: حالة كل رابط تقرأ حسابه (EvidenceLinkStatus)، فلا استعلام لكل صف
            ->modifyQueryUsing(fn (Builder $query) => $query->with('issuer')->withCount(['items', 'visits']))
            ->columns([
                TextColumn::make('title')->label(__('evidence.fields.title'))->searchable()->wrap(),
                TextColumn::make('status')
                    ->label(__('evidence.fields.link'))
                    ->badge()
                    ->state(fn (EvidenceGallery $record): EvidenceLinkStatus => self::status($record)),
                TextColumn::make('expires_at')->label(__('evidence.fields.expires_at'))->dateTime('Y-m-d H:i')->placeholder('—'),
                TextColumn::make('items_count')->label(__('evidence.fields.items'))->numeric(),
                TextColumn::make('visits_count')->label(__('evidence.fields.visits'))->numeric(),
                TextColumn::make('created_at')->label(__('evidence.fields.created_at'))->date('Y-m-d')->sortable(),
            ])
            ->filters([TrashedFilter::make()])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }

    public static function status(EvidenceGallery $gallery): EvidenceLinkStatus
    {
        return EvidenceLinkStatus::of($gallery);
    }

    /**
     * بيانات النموذج كما يأخذها SaveEvidenceGallery.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function toAction(array $data): array
    {
        $data['items'] = array_values(array_map(fn (array $item): array => [
            'source' => $item['source'] ?? null,
            'album_id' => ($item['source'] ?? null) === 'album' ? ($item['album_id'] ?? null) : null,
            'archive_item_id' => ($item['source'] ?? null) === 'archive' ? ($item['archive_item_id'] ?? null) : null,
            'paths' => array_values((array) ($item['paths'] ?? [])),
        ], (array) ($data['items'] ?? [])));

        return $data;
    }

    /**
     * والعكس عند فتح النموذج. والبند الذي غاب مصدره (ألبوم حُذف أو أُلغي نشره، أو شاهد حُذف من الأرشيف أو صار غير شاهد) لا يُعرض،
     * وما اختير منه يُقصر على ما بقي في مصدره. فإن لم يبق منه شيء حُذف البند، لا أن يصير «كله» فيُنشر ما لم يُختر.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function fromRecord(EvidenceGallery $gallery, array $data): array
    {
        $items = [];

        foreach ($gallery->items()->with(['album', 'archiveItem'])->get() as $item) {
            $source = $item->album_id !== null ? 'album' : 'archive';
            $available = match (true) {
                $item->album !== null && $item->album->is_published => $item->album->photoList(),
                $item->archiveItem !== null && $item->archiveItem->type === ArchiveItemType::Evidence => array_column($item->archiveItem->files ?? [], 'path'),
                default => null,
            };

            if ($available === null) {
                continue;
            }

            $paths = $item->paths === null ? [] : array_values(array_intersect($item->paths, $available));

            if ($item->paths !== null && $paths === []) {
                continue;
            }

            $items[] = ['source' => $source, 'album_id' => $item->album_id, 'archive_item_id' => $item->archive_item_id, 'paths' => $paths];
        }

        return [...$data, 'items' => $items];
    }

    private static function user(): User
    {
        /** @var User */
        return auth()->user();
    }

    private static function like(string $search): string
    {
        // حرفا % و_ في البحث حرفان عاديان، لا نمطان
        return '%'.addcslashes($search, '\\%_').'%';
    }

    /**
     * الألبومات المنشورة وحدها — كما في SaveEvidenceGallery.
     *
     * @return Builder<Album>
     */
    private static function albums(): Builder
    {
        return Album::query()->where('is_published', true);
    }

    /**
     * الشواهد في الأرشيف من التصنيفات التي يطّلع عليها الحساب.
     *
     * @return Builder<ArchiveItem>
     */
    private static function evidence(): Builder
    {
        return ArchiveItem::query()
            ->where('type', ArchiveItemType::Evidence)
            ->whereIn('archive_category_id', ArchiveCategory::visibleTo(self::user())->select('id'));
    }

    /**
     * عنوان الألبوم ومساراته، مرة في الطلب لكل ألبوم (البند يُرسم ويُتحقق منه مرات في الطلب الواحد).
     *
     * @return array{title: string, paths: list<string>}|null
     */
    private static function albumData(mixed $id): ?array
    {
        $id = (int) $id;

        return once(function () use ($id): ?array {
            $album = self::albums()->find($id);

            return $album === null ? null : ['title' => $album->title, 'paths' => $album->photoList()];
        });
    }

    /**
     * عنوان الشاهد وملفاته، مرة في الطلب لكل شاهد.
     *
     * @return array{title: string, files: list<array{path: string, name: string, size: int}>}|null
     */
    private static function evidenceData(mixed $id): ?array
    {
        $id = (int) $id;

        return once(function () use ($id): ?array {
            $item = self::evidence()->find($id);

            return $item === null ? null : ['title' => $item->title, 'files' => $item->files ?? []];
        });
    }

    private static function albumTitle(mixed $id): ?string
    {
        return filled($id) ? self::albumData($id)['title'] ?? null : null;
    }

    private static function evidenceTitle(mixed $id): ?string
    {
        return filled($id) ? self::evidenceData($id)['title'] ?? null : null;
    }

    /**
     * صور الألبوم بأرقامها، أو ملفات الشاهد بأسمائها، مفاتيحها مساراتها.
     *
     * @return array<string, string>
     */
    private static function sourceOptions(mixed $source, mixed $albumId, mixed $archiveId): array
    {
        if ($source === 'album' && filled($albumId)) {
            $photos = self::albumData($albumId)['paths'] ?? [];

            return collect($photos)->mapWithKeys(fn (string $path, int $i): array => [$path => __('evidence.fields.photo_option', ['number' => $i + 1])])->all();
        }

        if ($source === 'archive' && filled($archiveId)) {
            $files = self::evidenceData($archiveId)['files'] ?? [];

            return collect($files)->mapWithKeys(fn (array $file): array => [$file['path'] => $file['name']])->all();
        }

        return [];
    }

    /**
     * @param  array<string, mixed>  $state
     */
    private static function itemLabel(array $state): ?string
    {
        return match ($state['source'] ?? null) {
            'album' => self::albumTitle($state['album_id'] ?? null) ?? __('evidence.sources.album'),
            'archive' => self::evidenceTitle($state['archive_item_id'] ?? null) ?? __('evidence.sources.archive'),
            default => null,
        };
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

    public static function getRelations(): array
    {
        return [
            VisitsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvidenceGalleries::route('/'),
            'create' => CreateEvidenceGallery::route('/create'),
            'edit' => EditEvidenceGallery::route('/{record}/edit'),
        ];
    }
}
