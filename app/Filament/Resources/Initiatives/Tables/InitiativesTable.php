<?php

namespace App\Filament\Resources\Initiatives\Tables;

use App\Actions\Content\RecordContentReorder;
use App\Filament\Actions\DeleteContentAction;
use App\Models\Initiative;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InitiativesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء
            ->afterReordering(fn (array $order) => app(RecordContentReorder::class)->handle(Initiative::class, $order))
            ->columns([
                ImageColumn::make('image')->label('')->disk('public')->circular()->disabledClick(),
                TextColumn::make('name')->label(__('initiatives.fields.name'))->searchable(),
                TextColumn::make('slug')->label(__('content.slug'))->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصور، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
