<?php

namespace App\Filament\Resources\MenuItems\Tables;

use App\Actions\Content\RecordMenuReorder;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class MenuItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent')->withCount('children'))
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء خاص
            ->beforeReordering(fn () => app(RecordMenuReorder::class)->capture())
            ->afterReordering(fn (array $order) => app(RecordMenuReorder::class)->record($order))
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
                TextColumn::make('children_count')
                    ->label(__('menu.fields.children_count'))
                    ->numeric(),
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
