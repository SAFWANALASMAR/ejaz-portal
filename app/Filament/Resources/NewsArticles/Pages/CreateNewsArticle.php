<?php

namespace App\Filament\Resources\NewsArticles\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\NewsArticles\NewsArticleResource;
use App\Models\NewsArticle;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateNewsArticle extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = NewsArticleResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return $this->saveContent(new NewsArticle, $data);
    }
}
