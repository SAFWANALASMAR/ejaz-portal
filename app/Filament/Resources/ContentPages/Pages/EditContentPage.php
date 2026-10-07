<?php

namespace App\Filament\Resources\ContentPages\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\ContentPages\ContentPageResource;
use App\Models\Page;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditContentPage extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = ContentPageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteContentAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Page $record */
        return $this->saveContent($record, $data);
    }
}
