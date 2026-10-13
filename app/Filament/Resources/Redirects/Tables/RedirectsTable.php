<?php

namespace App\Filament\Resources\Redirects\Tables;

use App\Enums\RedirectType;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class RedirectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('source')
            ->columns([
                // المصدر محفوظ بلا شرطة في طرفيه، ويُعرض مساراً كما يُكتب
                TextColumn::make('source')
                    ->label(__('redirects.fields.source'))
                    ->formatStateUsing(fn (string $state): string => '/'.$state)
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('target')
                    ->label(__('redirects.fields.target'))
                    ->extraAttributes(['dir' => 'ltr'])
                    ->searchable()
                    ->wrap(),
                TextColumn::make('type')
                    ->label(__('redirects.fields.type'))
                    ->badge(),
                IconColumn::make('is_active')
                    ->label(__('redirects.fields.is_active'))
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label(__('redirects.fields.updated_at'))
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label(__('redirects.fields.type'))
                    ->options(RedirectType::class),
                TernaryFilter::make('is_active')
                    ->label(__('redirects.fields.is_active')),
            ])
            // حذف فردي فقط، حتى يمر كل حذف بأحداث النموذج (مسح الخريطة وسجل العمليات)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
