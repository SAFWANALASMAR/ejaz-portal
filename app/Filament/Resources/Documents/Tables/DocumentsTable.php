<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentCategory;
use App\Filament\Resources\Documents\DownloadDocumentAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('issued_at', 'desc')
            ->columns([
                TextColumn::make('title')->label(__('governance.fields.title'))->searchable()->wrap(),
                TextColumn::make('category')->label(__('governance.fields.category'))->badge(),
                TextColumn::make('issued_at')->label(__('governance.fields.issued_at'))->date('Y-m-d')->sortable(),
                TextColumn::make('version')->label(__('governance.fields.version'))->toggleable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('governance.fields.category'))->options(DocumentCategory::class),
                TrashedFilter::make(),
            ])
            // حذف ناعم فردي واستعادة، ولا حذف نهائي (DocumentPolicy)
            ->recordActions([
                DownloadDocumentAction::make(),
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
