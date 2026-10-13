<?php

namespace App\Filament\Resources\JobFiles\Pages;

use App\Actions\Activity\LogAccess;
use App\Actions\JobFiles\UpdateJobFileParticipants;
use App\Enums\ActivityEvent;
use App\Filament\Resources\JobFiles\JobFileResource;
use App\Models\JobFile;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * صفحة ملف الوظيفة ومستنداته، ويُسجَّل فتحها. والمسؤول عن الملف يحدد مشاركيه من هنا.
 */
class ViewJobFile extends ViewRecord
{
    protected static string $resource = JobFileResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        app(LogAccess::class)->handle(self::actor(), $this->getRecord(), 'job_files', ActivityEvent::Viewed);
    }

    public function getTitle(): string|Htmlable
    {
        /** @var JobFile $record */
        $record = $this->getRecord();

        return $record->title;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('participants')
                ->label(__('job_files.actions.participants'))
                ->icon(Heroicon::OutlinedUserGroup)
                ->color('gray')
                ->authorize(fn (JobFile $record): bool => self::actor()->can('manageParticipants', $record))
                ->fillForm(fn (JobFile $record): array => [
                    'participant_roles' => $record->participant_roles,
                    'participants_contribute' => $record->participants_contribute,
                ])
                ->schema(fn (JobFile $record): array => [
                    CheckboxList::make('participant_roles')
                        ->label(__('job_files.fields.participant_roles'))
                        ->options(JobFileResource::staffOptions($record->responsible_role->value))
                        ->columns(2),
                    Toggle::make('participants_contribute')
                        ->label(__('job_files.fields.participants_contribute'))
                        ->helperText(__('job_files.fields.participants_contribute_hint')),
                ])
                ->action(function (JobFile $record, array $data, UpdateJobFileParticipants $update): void {
                    $update->handle(self::actor(), $record, $data);
                    Notification::make()->success()->title(__('job_files.notifications.participants_saved'))->send();
                }),
            EditAction::make(),
        ];
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
