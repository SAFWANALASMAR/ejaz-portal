<?php

namespace App\Filament\Resources\RegisterEntries\Pages;

use App\Actions\Registers\UpdateRegisterDefaults;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Models\RegisterEntry;
use App\Models\User;
use App\Support\Registers;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListRegisterEntries extends ListRecords
{
    protected static string $resource = RegisterEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            // مدة التنبيه الافتراضية للمستندات الجديدة، لمن يدير السجلات
            Action::make('defaults')
                ->label(__('registers.actions.defaults'))
                ->icon(Heroicon::OutlinedBellAlert)
                ->color('gray')
                ->authorize(fn (): bool => auth()->user()?->can('create', RegisterEntry::class) ?? false)
                ->fillForm(fn (): array => ['default_alert_days' => Registers::defaultAlertDays()])
                ->schema([
                    TextInput::make('default_alert_days')->label(__('registers.fields.default_alert_days'))->integer()->minValue(1)->maxValue(730)->required(),
                ])
                ->action(function (array $data, UpdateRegisterDefaults $defaults): void {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $defaults->handle($actor, $data);
                    Notification::make()->success()->title(__('registers.actions.defaults_saved'))->send();
                }),
        ];
    }
}
