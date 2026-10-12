<?php

namespace App\Filament\Resources\JobFiles\Pages;

use App\Actions\JobFiles\SaveJobFile;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\JobFiles\JobFileResource;
use App\Models\JobFile;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditJobFile extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = JobFileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var JobFile $record */

        return $this->saveThroughAction($record, [], fn (): JobFile => app(SaveJobFile::class)->handle($actor, $record, $data));
    }

    protected function getRedirectUrl(): string
    {
        return JobFileResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
