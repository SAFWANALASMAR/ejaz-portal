<?php

namespace App\Filament\Resources\Albums\Tables;

use App\Filament\Actions\DeleteContentAction;
use App\Models\Album;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AlbumsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->scopes('newestFirst'))
            ->columns([
                ImageColumn::make('cover')->label('')->disk('public')->state(fn (Album $record): ?string => $record->cover()),
                TextColumn::make('title')->label(__('albums.fields.title'))->searchable()->wrap(),
                TextColumn::make('photos_count')->label(__('albums.fields.photos_count'))->state(fn (Album $record): int => count($record->photoList())),
                TextColumn::make('published_on')->label(__('albums.fields.published_on'))->date('Y-m-d')->sortable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label(__('content.is_published')),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بالإجراء (الصور، وسجل العمليات، والذاكرة)
            ->recordActions([
                EditAction::make(),
                DeleteContentAction::make(),
            ]);
    }
}
