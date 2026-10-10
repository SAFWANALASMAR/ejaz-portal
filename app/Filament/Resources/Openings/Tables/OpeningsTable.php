<?php

namespace App\Filament\Resources\Openings\Tables;

use App\Enums\OpeningKind;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OpeningsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount(['requests' => fn ($requests) => $requests->submitted()]))
            ->columns([
                TextColumn::make('title')->label(__('services.openings.fields.title'))->searchable()->wrap(),
                TextColumn::make('kind')->label(__('services.openings.fields.kind'))->badge(),
                TextColumn::make('closes_at')->label(__('services.openings.fields.closes_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('requests_count')->label(__('services.openings.fields.requests_count'))->numeric(),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('kind')->label(__('services.openings.fields.kind'))->options(OpeningKind::class),
                TernaryFilter::make('is_published')->label(__('content.is_published')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
