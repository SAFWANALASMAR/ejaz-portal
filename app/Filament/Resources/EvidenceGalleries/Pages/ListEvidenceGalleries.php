<?php

namespace App\Filament\Resources\EvidenceGalleries\Pages;

use App\Filament\Resources\EvidenceGalleries\EvidenceGalleryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEvidenceGalleries extends ListRecords
{
    protected static string $resource = EvidenceGalleryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
