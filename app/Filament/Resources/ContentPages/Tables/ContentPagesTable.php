<?php

namespace App\Filament\Resources\ContentPages\Tables;

use App\Filament\Actions\DeleteContentAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContentPagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('title')
            ->columns([
                TextColumn::make('title')->label(__('pages.fields.title'))->searchable(),
                TextColumn::make('slug')->label(__('content.slug'))->extraAttributes(['dir' => 'ltr']),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
                TextColumn::make('updated_at')->label(__('pages.fields.updated_at'))->since()->sortable(),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصور، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
