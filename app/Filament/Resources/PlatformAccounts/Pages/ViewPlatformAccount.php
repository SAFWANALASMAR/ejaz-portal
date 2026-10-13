<?php

namespace App\Filament\Resources\PlatformAccounts\Pages;

use App\Actions\Activity\LogAccess;
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
        app(LogAccess::class)->handle($actor, $this->getRecord(), 'registers', ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        return [EditAction::make()];
    }
}
