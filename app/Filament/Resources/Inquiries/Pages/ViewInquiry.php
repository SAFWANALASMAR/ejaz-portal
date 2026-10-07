<?php

namespace App\Filament\Resources\Inquiries\Pages;

use App\Actions\Contact\UpdateInquiry;
use App\Enums\InquiryStatus;
use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * عرض الرسالة ومعالجتها: الحالة والملاحظة لمن له requests.update، والإسناد لمن له requests.assign.
 */
class ViewInquiry extends ViewRecord
{
    protected static string $resource = InquiryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('process')
                ->label(__('inquiries.process'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->authorize(fn (Inquiry $record): bool => auth()->user()?->can('update', $record) ?? false)
                ->fillForm(fn (Inquiry $record): array => [
                    'status' => $record->status->value,
                    'assigned_to' => $record->assigned_to,
                    'internal_note' => $record->internal_note,
                ])
                ->schema([
                    Select::make('status')->label(__('inquiries.fields.status'))->options(InquiryStatus::class)->required(),
                    // المعطّل لا يُرسل، فلا يغيّر الإسناد من لا يملكه
                    Select::make('assigned_to')
                        ->label(__('inquiries.fields.assignee'))
                        ->options(fn (): array => UpdateInquiry::assignees())
                        ->placeholder(__('inquiries.unassigned'))
                        ->disabled(fn (Inquiry $record): bool => ! (auth()->user()?->can('assign', $record) ?? false)),
                    Textarea::make('internal_note')
                        ->label(__('inquiries.fields.internal_note'))
                        ->helperText(__('inquiries.fields.internal_note_hint'))
                        ->rows(4)
                        ->maxLength(2000),
                ])
                ->action(function (Inquiry $record, array $data, Action $action): void {
                    /** @var User $actor */
                    $actor = auth()->user();

                    try {
                        app(UpdateInquiry::class)->handle($actor, $record, $data);
                    } catch (ValidationException $exception) {
                        Notification::make()->title((string) collect($exception->errors())->flatten()->first())->danger()->send();
                        $action->halt();
                    }

                    Notification::make()->title(__('inquiries.processed'))->success()->send();
                }),
            DeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
