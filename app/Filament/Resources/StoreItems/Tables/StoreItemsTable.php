<?php

namespace App\Filament\Resources\StoreItems\Tables;

use App\Actions\Content\RecordContentReorder;
use App\Enums\StoreItemCategory;
use App\Filament\Actions\DeleteContentAction;
use App\Models\StoreItem;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StoreItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء
            ->afterReordering(fn (array $order) => app(RecordContentReorder::class)->handle(StoreItem::class, $order))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->disabledClick(),
                TextColumn::make('title')->label(__('store.admin.fields.title'))->searchable(),
                TextColumn::make('category')->label(__('store.admin.fields.category'))->badge(),
                TextColumn::make('salla_product_id')->label(__('store.admin.fields.salla_product_id'))->extraAttributes(['dir' => 'ltr'])->placeholder('—')->toggleable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('store.admin.fields.category'))->options(StoreItemCategory::class),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصورة، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
