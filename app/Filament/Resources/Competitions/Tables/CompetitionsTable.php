<?php

namespace App\Filament\Resources\Competitions\Tables;

use App\Enums\CompetitionStatus;
use App\Models\Competition;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class CompetitionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('opens_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('questions'))
            ->columns([
                TextColumn::make('title')->label(__('competitions.fields.title'))->searchable()->wrap(),
                TextColumn::make('status')
                    ->label(__('competitions.fields.status'))
                    ->badge()
                    ->state(fn (Competition $record): CompetitionStatus => $record->status()),
                TextColumn::make('opens_at')->label(__('competitions.fields.opens_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('closes_at')->label(__('competitions.fields.closes_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('questions_count')->label(__('competitions.fields.questions_count'))->numeric(),
            ])
            ->filters([
                TernaryFilter::make('is_published')->label(__('content.is_published')),
                TrashedFilter::make(),
            ])
            // حذف ناعم فردي واستعادة، ولا حذف نهائي (CompetitionPolicy)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
