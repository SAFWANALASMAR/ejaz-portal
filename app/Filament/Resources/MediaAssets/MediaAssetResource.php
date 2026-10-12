<?php

namespace App\Filament\Resources\MediaAssets;

use App\Filament\Concerns\HasTranslatedLabels;
use App\Filament\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Resources\MediaAssets\Schemas\MediaAssetForm;
use App\Filament\Resources\MediaAssets\Tables\MediaAssetsTable;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * مكتبة الوسائط: صور الموقع السابق وأيقوناته، للاطلاع وإكمال النص البديل. الصلاحيات:
 * media_assets.* — MediaAssetPolicy. والإضافة بأمر الاستيراد.
 */
class MediaAssetResource extends Resource
{
    use HasTranslatedLabels;

    protected static string $langKey = 'media_assets';

    protected static string $navigationGroupKey = 'content';

    protected static ?string $model = MediaAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?int $navigationSort = 85;

    public static function form(Schema $schema): Schema
    {
        return MediaAssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaAssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
