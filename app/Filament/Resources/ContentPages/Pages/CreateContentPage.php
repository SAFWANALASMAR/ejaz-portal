<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Models\Page;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateContentPage extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = ContentPageResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new Page, $data);
    }
}
