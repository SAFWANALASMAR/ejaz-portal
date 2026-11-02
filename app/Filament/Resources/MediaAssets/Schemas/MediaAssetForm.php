<?php

namespace App\Filament\Resources\MediaAssets\Schemas;

use App\Models\MediaAsset;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * العنوان والنص البديل وكونها زخرفية، وفحوصها في SaveMediaAsset. والملف وبياناته للاطلاع.
 */
class MediaAssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->label(__('media_assets.fields.title'))
                            ->required()
                            ->maxLength(200),
                        Toggle::make('is_decorative')
                            ->label(__('media_assets.fields.is_decorative'))
                            ->helperText(__('media_assets.fields.is_decorative_hint'))
                            ->live(),
                        TextInput::make('alt')
                            ->label(__('media_assets.fields.alt'))
                            ->helperText(__('media_assets.fields.alt_hint'))
                            ->maxLength(300)
                            ->hidden(fn (Get $get): bool => (bool) $get('is_decorative'))
                            ->columnSpanFull(),
                    ]),
                Section::make(__('media_assets.file'))
                    ->columns(2)
                    ->schema([
                        ImageEntry::make('web_path')
                            ->label(__('media_assets.file'))->hiddenLabel()
                            ->disk('public')
                            ->imageHeight(200)
                            ->visible(fn (?MediaAsset $record): bool => $record?->web_path !== null)
                            ->columnSpanFull(),
                        TextEntry::make('original_name')->label(__('media_assets.fields.original_name')),
                        TextEntry::make('mime_type')->label(__('media_assets.fields.mime_type')),
                        TextEntry::make('dimensions')->label(__('media_assets.fields.dimensions'))
                            ->state(fn (MediaAsset $record): string => $record->width !== null ? $record->width.'×'.$record->height : '—'),
                        TextEntry::make('from_template')->label(__('media_assets.fields.from_template'))
                            ->state(fn (MediaAsset $record): string => __($record->from_template ? 'media_assets.template.yes' : 'media_assets.template.no')),
                    ]),
            ]);
    }
}
