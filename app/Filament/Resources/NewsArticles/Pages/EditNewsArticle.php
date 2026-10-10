<?php

namespace App\Filament\Resources\NewsArticles\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Models\NewsArticle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNewsArticle extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = NewsArticleResource::class;

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
        /** @var NewsArticle $record */
        return $this->saveContent($record, $data);
    }
}
