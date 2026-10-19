<?php

namespace App\Filament\Resources\MediaAssets\Tables;

use App\Models\MediaAsset;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MediaAssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                ImageColumn::make('web_path')
                    ->label(__('media_assets.fields.preview'))
                    ->disk('public')
                    ->disabledClick()
                    ->imageHeight(48),
                TextColumn::make('title')
                    ->label(__('media_assets.fields.title'))
                    ->searchable()
                    ->wrap(),
                TextColumn::make('alt')
                    ->label(__('media_assets.fields.alt'))
                    ->state(fn (MediaAsset $record): string => $record->is_decorative ? __('media_assets.decorative') : (string) $record->alt)
                    ->wrap()
                    // على الجوال المعاينة والعنوان وحدهما: برأس الجدول كاملاً يتمرر حاويه الفارغ ولا تصله لوحة المفاتيح
                    // (إعادة التجربة الشاملة، 19 أكتوبر 2026). والنص البديل في صفحة التعديل
                    ->visibleFrom('md'),
                IconColumn::make('from_template')
                    ->label(__('media_assets.fields.from_template'))
                    ->boolean()
                    ->visibleFrom('md'),
                TextColumn::make('original_name')
                    ->label(__('media_assets.fields.original_name'))
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('from_template')->label(__('media_assets.fields.from_template')),
                TernaryFilter::make('is_decorative')->label(__('media_assets.fields.is_decorative')),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
