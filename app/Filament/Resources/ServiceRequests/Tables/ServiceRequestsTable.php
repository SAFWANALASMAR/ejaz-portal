<?php

namespace App\Filament\Resources\ServiceRequests\Tables;

use App\Enums\OpeningKind;
use App\Enums\ServiceRequestStatus;
use App\Filament\Resources\ServiceRequests\ServiceRequestActions;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ServiceRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('submitted_at', 'desc')
            ->columns([
                TextColumn::make('reference')->label(__('services.admin.fields.reference'))->searchable(),
                TextColumn::make('opening.title')->label(__('services.admin.fields.opening'))->wrap(),
                TextColumn::make('user.name')->label(__('services.admin.fields.applicant'))->searchable(),
                TextColumn::make('status')->label(__('services.admin.fields.status'))->badge(),
                TextColumn::make('assignee.name')->label(__('services.admin.fields.assignee'))->placeholder('—'),
                TextColumn::make('submitted_at')->label(__('services.admin.fields.submitted_at'))->dateTime('Y-m-d H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->label(__('services.admin.fields.status'))
                    ->options(collect(ServiceRequestStatus::cases())->reject(fn ($status) => $status === ServiceRequestStatus::Draft)->mapWithKeys(fn ($status) => [$status->value => $status->getLabel()])),
                SelectFilter::make('kind')->label(__('services.admin.fields.kind'))->options(OpeningKind::class)
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, string $kind): Builder => $query->whereHas('opening', fn (Builder $opening) => $opening->where('kind', $kind)),
                    )),
                SelectFilter::make('opening')->label(__('services.admin.fields.opening'))->relationship('opening', 'title')->searchable()->preload(),
                Filter::make('mine')->label(__('services.admin.filters.mine'))->query(fn (Builder $query): Builder => $query->where('assigned_to', auth()->id())),
                Filter::make('unassigned')->label(__('services.admin.filters.unassigned'))->query(fn (Builder $query): Builder => $query->whereNull('assigned_to')),
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                ServiceRequestActions::changeStatus(),
                ServiceRequestActions::assign(),
                DeleteAction::make(),
                RestoreAction::make(),
            ]);
    }
}
