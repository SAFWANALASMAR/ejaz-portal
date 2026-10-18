<?php

namespace App\Filament\Resources\BoardMembers\Tables;

use App\Actions\Content\RecordContentReorder;
use App\Filament\Actions\DeleteContentAction;
use App\Models\BoardMember;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BoardMembersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort')
            ->reorderable('sort')
            // الترتيب يُحفظ باستعلام واحد لا يمر بأحداث النموذج، فيُسجَّل ويُمسح التخزين بإجراء
            ->afterReordering(fn (array $order) => app(RecordContentReorder::class)->handle(BoardMember::class, $order))
            ->columns([
                ImageColumn::make('photo')->label('')->disk('public')->circular()->disabledClick(),
                TextColumn::make('name')->label(__('board.fields.name'))->searchable(),
                TextColumn::make('position')->label(__('board.fields.position')),
                IconColumn::make('is_visible')->label(__('board.fields.is_visible'))->boolean(),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصور، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
