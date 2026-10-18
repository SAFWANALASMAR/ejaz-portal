<?php

namespace App\Filament\Resources\Partners\Tables;

use App\Actions\Content\RecordContentReorder;
use App\Filament\Actions\DeleteContentAction;
use App\Models\Partner;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PartnersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء
            ->afterReordering(fn (array $order) => app(RecordContentReorder::class)->handle(Partner::class, $order))
            ->columns([
                ImageColumn::make('logo')->label('')->disk('public')->disabledClick(),
                TextColumn::make('name')->label(__('partners.fields.name'))->searchable(),
                TextColumn::make('kind')->label(__('partners.fields.kind'))->badge(),
                TextColumn::make('url')->label(__('partners.fields.url'))->extraAttributes(['dir' => 'ltr'])->toggleable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الشعار، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
