<?php

namespace App\Filament\Resources\EvidenceGalleries\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل فتح المعرض برابطه السري: الوقت والشبكة المقنّعة وحدهما، للقراءة. يطّلع عليه من يطّلع على المعرض.
 */
class VisitsRelationManager extends RelationManager
{
    protected static string $relationship = 'visits';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('evidence.fields.visits');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modelLabel(__('evidence.fields.visit'))
            ->pluralModelLabel(__('evidence.fields.visits'))
            ->defaultSort('visited_at', 'desc')
            ->columns([
                TextColumn::make('visited_at')->label(__('evidence.fields.visited_at'))->dateTime('Y-m-d H:i'),
                TextColumn::make('network')->label(__('evidence.fields.network'))->extraAttributes(['dir' => 'ltr']),
            ]);
    }
}
