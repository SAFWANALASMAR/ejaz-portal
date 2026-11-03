<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Actions\DeleteContentAction;
use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Models\SocialPost;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditSocialPost extends EditRecord
{
    use SavesContentThroughAction;

    protected static string $resource = SocialPostResource::class;

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
        /** @var SocialPost $record */
        return $this->saveContent($record, $data);
    }
}
