<?php

namespace App\Filament\Resources\LibraryItems;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\LibraryItems\Pages\CreateLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\EditLibraryItem;
use App\Filament\Resources\LibraryItems\Pages\ListLibraryItems;
use App\Filament\Resources\LibraryItems\Schemas\LibraryItemForm;
use App\Filament\Resources\LibraryItems\Tables\LibraryItemsTable;
use App\Models\LibraryItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * عناصر المكتبة. الصلاحيات: library.* — LibraryItemPolicy. الحذف ناعم ويُستعاد.
 */
class LibraryItemResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'library';

    protected static string $navigationGroupKey = 'library';

    protected static ?string $model = LibraryItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingLibrary;

    public static function form(Schema $schema): Schema
    {
        return LibraryItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LibraryItemsTable::configure($table);
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
            'index' => ListLibraryItems::route('/'),
            'create' => CreateLibraryItem::route('/create'),
            'edit' => EditLibraryItem::route('/{record}/edit'),
        ];
    }
}
