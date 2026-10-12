<?php

namespace App\Filament\Resources\PlatformAccounts\Pages;

use App\Actions\Registers\LogRegisterAccess;
use App\Enums\ActivityEvent;
use App\Filament\Resources\PlatformAccounts\PlatformAccountResource;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * صفحة حساب المنصة، ويُسجَّل فتحها.
 */
class ViewPlatformAccount extends ViewRecord
{
    protected static string $resource = PlatformAccountResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $actor */
        $actor = auth()->user();
        app(LogRegisterAccess::class)->handle($actor, $this->getRecord(), ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
