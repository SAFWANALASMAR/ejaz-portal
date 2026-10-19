<?php

namespace App\Filament\Resources\AssistantLogs\Pages;

use App\Actions\Assistant\FlagAssistantAnswer;
use App\Filament\Resources\AssistantLogs\AssistantLogResource;
use App\Models\AssistantLog;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

/**
 * عرض المحادثة، وتعليم الإجابة الخاطئة بملاحظة التصحيح أو إلغاؤه.
 */
class ViewAssistantLog extends ViewRecord
{
    protected static string $resource = AssistantLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('flag')
                ->label(__('assistant.admin.logs.flag'))
                ->icon(Heroicon::OutlinedFlag)
                ->color('danger')
                ->visible(fn (AssistantLog $record): bool => ! $record->is_flagged)
                ->authorize(fn (AssistantLog $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->schema([
                    Textarea::make('flag_note')->label(__('assistant.admin.logs.flag_note'))->helperText(__('assistant.admin.logs.flag_note_hint'))->rows(3)->maxLength(1000),
                ])
                ->action(function (AssistantLog $record, array $data): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    app(FlagAssistantAnswer::class)->handle($actor, $record, true, $data['flag_note'] ?? null);
                    Notification::make()->success()->title(__('assistant.admin.logs.flagged'))->send();
                }),
            Action::make('unflag')
                ->label(__('assistant.admin.logs.unflag'))
                ->color('gray')
                ->visible(fn (AssistantLog $record): bool => $record->is_flagged)
                ->authorize(fn (AssistantLog $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->requiresConfirmation()
                ->action(function (AssistantLog $record): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    app(FlagAssistantAnswer::class)->handle($actor, $record, false);
                }),
        ];
    }
}
