<?php

namespace App\Filament\Resources\StoreItems;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\StoreItems\Pages\CreateStoreItem;
use App\Filament\Resources\StoreItems\Pages\EditStoreItem;
use App\Filament\Resources\StoreItems\Pages\ListStoreItems;
use App\Filament\Resources\StoreItems\Schemas\StoreItemForm;
use App\Filament\Resources\StoreItems\Tables\StoreItemsTable;
use App\Models\StoreItem;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * «المتجر» في مجموعة المحتوى: عناصر صفحة «المتجر والتبرعات» (قرار صفوان، 4 نوفمبر 2026). الصلاحيات: store_items.* —
 * StoreItemPolicy، لمحرر المحتوى ومديري النظام.
 */
class StoreItemResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'store.admin';

    protected static string $navigationGroupKey = 'content';

    protected static ?string $model = StoreItem::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return StoreItemForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StoreItemsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListStoreItems::route('/'),
            'create' => CreateStoreItem::route('/create'),
            'edit' => EditStoreItem::route('/{record}/edit'),
        ];
    }
}
