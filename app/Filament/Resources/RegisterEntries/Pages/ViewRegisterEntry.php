<?php

namespace App\Filament\Resources\RegisterEntries\Pages;

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Filament\Resources\RegisterEntries\RegisterEntryResource;
use App\Models\RegisterEntry;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * صفحة المستند، ويُسجَّل فتحها (كل اطلاع يُسجَّل — requirements/13 §1).
 */
class ViewRegisterEntry extends ViewRecord
{
    protected static string $resource = RegisterEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $actor */
        $actor = auth()->user();
        app(LogAccess::class)->handle($actor, $this->getRecord(), 'registers', ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        /** @var RegisterEntry $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),
            ...RegisterEntryResource::downloads($record),
        ];
    }
}
