<?php

namespace App\Filament\Resources\Meetings\Pages;

use App\Actions\Assembly\SaveMeeting;
use App\Filament\Concerns\SavesThroughAction;
use App\Filament\Resources\Meetings\MeetingResource;
use App\Filament\Resources\Meetings\Schemas\MeetingForm;
use App\Models\Meeting;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditMeeting extends EditRecord
{
    use SavesThroughAction;

    protected static string $resource = MeetingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Meeting $record */
        $record = $this->getRecord();

        return MeetingForm::fromRecord($record, $data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var Meeting $record */

        return $this->saveThroughAction($record, ['attachments'], fn (): Meeting => app(SaveMeeting::class)->handle($actor, $record, MeetingForm::toAction($data)));
    }

    protected function getRedirectUrl(): string
    {
        return MeetingResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
