<?php

namespace App\Filament\Resources\SocialPosts\Pages;

use App\Filament\Concerns\SavesContentThroughAction;
use App\Filament\Resources\SocialPosts\Schemas\SocialPostForm;
use App\Filament\Resources\SocialPosts\SocialPostResource;
use App\Models\SocialPost;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSocialPost extends CreateRecord
{
    use SavesContentThroughAction;

    protected static string $resource = SocialPostResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return SocialPostForm::saveOnce(fn (): Model => $this->saveContent(new SocialPost, $data));
    }
}
