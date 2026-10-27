<?php

namespace App\Filament\Resources\ArchiveCategories;

use App\Actions\Documents\SaveArchiveCategory;
use App\Enums\RoleName;
use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\ArchiveCategories\Pages\ManageArchiveCategories;
use App\Models\ArchiveCategory;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * تصنيفات الأرشيف بأدوار من يطّلع على عناصرها، للمشرف العام وحده (ArchiveCategoryPolicy). والحفظ عبر
 * SaveArchiveCategory.
 */
class ArchiveCategoryResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'documents.categories';

    protected static string $navigationGroupKey = 'archive';

    protected static ?string $model = ArchiveCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label(__('documents.fields.name'))->required()->maxLength(150)->columnSpanFull(),
            CheckboxList::make('roles')
                ->label(__('documents.fields.roles'))
                ->helperText(__('documents.fields.roles_hint'))
                ->options(collect(RoleName::panelRoles())->reject(fn (RoleName $role): bool => in_array($role, RoleName::overseers(), true))->mapWithKeys(fn (RoleName $role): array => [$role->value => $role->getLabel()])->all())
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            // وجود العناصر ولو محذوفة لصلاحية الحذف (ArchiveCategoryPolicy)، باستعلام الجدول نفسه
            ->modifyQueryUsing(fn (Builder $query) => $query->withExists(['items as has_any_items' => fn ($items) => $items->withTrashed()]))
            ->columns([
                TextColumn::make('name')->label(__('documents.fields.name'))->searchable(),
                TextColumn::make('roles')
                    ->label(__('documents.fields.roles'))
                    ->formatStateUsing(fn (string $state): string => RoleName::tryFrom($state)?->getLabel() ?? $state)
                    ->badge()
                    // على الجوال الاسم والعدد وحدهما: برأس الجدول كاملاً يتمرر حاويه ولا تصله لوحة المفاتيح
                    // (axe: scrollable-region-focusable، الاختبار الشامل 0.14 الخلل 5). والأدوار في نافذة التعديل
                    ->visibleFrom('md'),
                TextColumn::make('items_count')->label(__('documents.items.plural'))->counts('items'),
            ])
            ->recordActions([
                EditAction::make()->using(fn (ArchiveCategory $record, array $data): Model => self::save($record, $data)),
                DeleteAction::make(),
            ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(ArchiveCategory $category, array $data): ArchiveCategory
    {
        /** @var User $actor */
        $actor = auth()->user();

        return app(SaveArchiveCategory::class)->handle($actor, $category, ['name' => $data['name'] ?? null, 'roles' => array_values((array) ($data['roles'] ?? []))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageArchiveCategories::route('/'),
        ];
    }
}
