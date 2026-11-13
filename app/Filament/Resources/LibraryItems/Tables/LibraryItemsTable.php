<?php

namespace App\Filament\Resources\LibraryItems\Tables;

use App\Enums\LibrarySection;
use App\Filament\Resources\LibraryItems\DownloadLibraryFileAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LibraryItemsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->scopes('newestFirst'))
            ->columns([
                ImageColumn::make('thumbnail')->label('')->disk('public')->disabledClick(),
                TextColumn::make('title')->label(__('library.fields.title'))->searchable()->wrap(),
                TextColumn::make('section')->label(__('library.fields.section'))->badge(),
                TextColumn::make('author')->label(__('library.fields.author'))->searchable()->toggleable(),
                TextColumn::make('published_on')->label(__('library.fields.published_on'))->date('Y-m-d')->sortable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
                IconColumn::make('is_unlisted')->label(__('library.fields.is_unlisted'))->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('section')->label(__('library.fields.section'))->options(LibrarySection::class),
                TernaryFilter::make('is_published')->label(__('content.is_published')),
                TernaryFilter::make('is_unlisted')->label(__('library.fields.is_unlisted')),
                TrashedFilter::make(),
            ])
            // حذف ناعم فردي واستعادة، ولا حذف نهائي (LibraryItemPolicy)
            ->recordActions([
                DownloadLibraryFileAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
