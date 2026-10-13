<?php

namespace App\Filament\Resources\JobFiles\Pages;

use App\Actions\JobFiles\SaveJobFile;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\JobFiles\JobFileResource;
use App\Models\JobFile;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateJobFile extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = JobFileResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $file = new JobFile;

        return $this->saveThroughAction($file, [], fn (): JobFile => app(SaveJobFile::class)->handle($actor, $file, $data));
    }

    protected function getRedirectUrl(): string
    {
        return JobFileResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
