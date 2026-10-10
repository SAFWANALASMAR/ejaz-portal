<?php

namespace App\Filament\Resources\FormEntries\Pages;

use App\Actions\Documents\LogDocumentAccess;
use App\Enums\ActivityEvent;
use App\Filament\Resources\FormEntries\FormEntryResource;
use App\Models\FormEntry;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * صفحة المعبأ، ويُسجَّل فتحها (كل اطلاع يُسجَّل — requirements/05).
 */
class ViewFormEntry extends ViewRecord
{
    protected static string $resource = FormEntryResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $actor */
        $actor = auth()->user();
        app(LogDocumentAccess::class)->handle($actor, $this->getRecord(), ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        /** @var FormEntry $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),
            FormEntryResource::approveAction(),
            FormEntryResource::printAction(),
            ...FormEntryResource::downloads($record),
        ];
    }
}
