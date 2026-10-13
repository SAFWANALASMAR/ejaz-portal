<?php

namespace App\Filament\Resources\Documents\Tables;

use App\Enums\DocumentCategory;
use App\Filament\Resources\Documents\DownloadDocumentAction;
use App\Models\Document;
use Carbon\CarbonInterface;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class DocumentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query): Builder => $query->scopes('newestFirst'))
            ->columns([
                TextColumn::make('title')->label(__('governance.fields.title'))->searchable()->wrap(),
                TextColumn::make('category')->label(__('governance.fields.category'))->badge(),
                TextColumn::make('issued_at')->label(__('governance.fields.issued_at'))->date('Y-m-d')->sortable(),
                TextColumn::make('version')->label(__('governance.fields.version'))->toggleable(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('category')->label(__('governance.fields.category'))->options(DocumentCategory::class),
                SelectFilter::make('year')
                    ->label(__('governance.filters.year'))
                    ->options(fn (): array => Document::withTrashed()->whereNotNull('issued_at')->pluck('issued_at')
                        ->mapWithKeys(fn (CarbonInterface $date): array => [$date->year => (string) $date->year])
                        ->sortKeysDesc()
                        ->all())
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, int|string $year): Builder => $query->whereYear('issued_at', (int) $year),
                    )),
                TernaryFilter::make('is_published')->label(__('content.is_published')),
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
