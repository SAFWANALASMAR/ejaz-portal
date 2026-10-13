<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Actions\Assembly\SaveMeeting;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Models\Meeting;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateMeeting extends CreateRecord
{
    use SavesThroughAction;

    protected static string $resource = MeetingResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        $meeting = new Meeting;

        return $this->saveThroughAction($meeting, ['attachments'], fn (): Meeting => app(SaveMeeting::class)->handle($actor, $meeting, MeetingForm::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return MeetingResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
