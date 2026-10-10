<?php

namespace App\Filament\Resources\ArchiveCategories\Pages;

use App\Filament\Resources\ArchiveCategories\ArchiveCategoryResource;
use App\Models\ArchiveCategory;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Database\Eloquent\Model;

class ManageArchiveCategories extends ManageRecords
{
    protected static string $resource = ArchiveCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): Model => ArchiveCategoryResource::save(new ArchiveCategory, $data)),
        ];
    }
}
