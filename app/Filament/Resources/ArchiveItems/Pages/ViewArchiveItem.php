<?php

namespace App\Filament\Resources\ArchiveItems\Pages;

use App\Actions\Documents\LogDocumentAccess;
use App\Enums\ActivityEvent;
use App\Filament\Resources\ArchiveItems\ArchiveItemResource;
use App\Models\ArchiveItem;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

/**
 * صفحة عنصر الأرشيف، ويُسجَّل فتحها (كل اطلاع يُسجَّل — requirements/05).
 */
class ViewArchiveItem extends ViewRecord
{
    protected static string $resource = ArchiveItemResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        /** @var User $actor */
        $actor = auth()->user();
        app(LogDocumentAccess::class)->handle($actor, $this->getRecord(), ActivityEvent::Viewed);
    }

    protected function getHeaderActions(): array
    {
        /** @var ArchiveItem $record */
        $record = $this->getRecord();

        return [
            EditAction::make(),
            ...ArchiveItemResource::downloads($record),
        ];
    }
}
