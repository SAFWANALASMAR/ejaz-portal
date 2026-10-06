<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Models\MenuItem;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُمسح التخزين ويُسجَّل هنا
            ->afterReordering(function (array $order): void {
                Cache::forget(MenuItem::CACHE_KEY);
                activity('content')->event('menu_reordered')->withProperties(['order' => array_values($order)])->log('menu_reordered');
            })
            ->columns([
                TextColumn::make('label')
                    ->label(__('menu.fields.label'))
                    ->searchable(),
                TextColumn::make('parent.label')
                    ->label(__('menu.fields.parent'))
                    ->placeholder(__('menu.fields.parent_none')),
                TextColumn::make('url')
                    ->label(__('menu.fields.url'))
                    ->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('is_visible')
                    ->label(__('menu.fields.is_visible'))
                    ->boolean(),
                TextColumn::make('sort')
                    ->label(__('menu.fields.sort'))
                    ->numeric(),
            ])
            ->filters([
                TernaryFilter::make('top_level')
                    ->label(__('menu.fields.parent_none'))
                    ->queries(
                        true: fn ($query) => $query->whereNull('parent_id'),
                        false: fn ($query) => $query->whereNotNull('parent_id'),
                    ),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بأحداث النموذج (مسح التخزين وسجل العمليات)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
