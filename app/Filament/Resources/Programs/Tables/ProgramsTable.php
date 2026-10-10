<?php

namespace App\Filament\Resources\Programs\Tables;

use App\Models\Program;
use App\Models\ProgramType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class ProgramsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withConfirmedCount()->with('type'))
            ->columns([
                TextColumn::make('title')->label(__('programs.admin.fields.title'))->searchable()->wrap(),
                TextColumn::make('type.name')->label(__('programs.admin.fields.type')),
                TextColumn::make('starts_at')->label(__('programs.admin.fields.starts_at'))->dateTime('Y-m-d H:i')->sortable(),
                TextColumn::make('status')->label(__('programs.admin.fields.status'))->badge()->state(fn (Program $record) => $record->status()),
                TextColumn::make('confirmed_count')->label(__('programs.admin.fields.confirmed'))
                    ->formatStateUsing(fn (int $state, Program $record): string => number_format($state).($record->seats ? ' / '.number_format($record->seats) : '')),
                IconColumn::make('is_published')->label(__('content.is_published'))->boolean(),
            ])
            ->filters([
                SelectFilter::make('program_type_id')->label(__('programs.admin.fields.type'))->options(fn (): array => ProgramType::query()->ordered()->pluck('name', 'id')->all()),
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
