<?php

namespace App\Filament\Resources\ServiceRequests;

use App\Actions\Services\AssignServiceRequest;
use App\Actions\Services\ChangeServiceRequestStatus;
use App\Enums\FormFieldType;
use App\Enums\ServiceRequestStatus;
use App\Models\ServiceRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * إجراءات الطلب في اللوحة: تغيير الحالة، والإسناد، وتنزيل المرفقات. كلها تستدعي إجراءات الوحدة
 * التي تفحص الصلاحية بنفسها.
 */
final class ServiceRequestActions
{
    public static function changeStatus(): Action
    {
        return Action::make('changeStatus')
            ->label(__('services.admin.actions.change_status'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->visible(fn (ServiceRequest $record): bool => (auth()->user()?->can('update', $record) ?? false) && $record->status->staffTransitions() !== [])
            ->schema(fn (ServiceRequest $record): array => [
                Select::make('status')
                    ->label(__('services.admin.actions.new_status'))
                    ->options(collect($record->status->staffTransitions())->mapWithKeys(fn (ServiceRequestStatus $status): array => [$status->value => $status->getLabel()]))
                    ->required(),
                Textarea::make('message')->label(__('services.admin.fields.message'))->helperText(__('services.admin.fields.message_hint'))->rows(3)->maxLength(2000),
                Textarea::make('internal_note')->label(__('services.admin.fields.internal_note'))->helperText(__('services.admin.fields.internal_note_hint'))->rows(2)->maxLength(2000),
            ])
            ->action(function (ServiceRequest $record, array $data, ChangeServiceRequestStatus $change): void {
                /** @var User $actor */
                $actor = auth()->user();

                try {
                    $change->handle($actor, $record, ServiceRequestStatus::from((string) $data['status']), $data['message'] ?? null, $data['internal_note'] ?? null);
                } catch (AuthorizationException|ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('services.admin.status_changed'))->send();
            });
    }

    public static function assign(): Action
    {
        return Action::make('assign')
            ->label(__('services.admin.actions.assign'))
            ->icon(Heroicon::OutlinedUserPlus)
            ->visible(fn (ServiceRequest $record): bool => auth()->user()?->can('assign', $record) ?? false)
            ->fillForm(fn (ServiceRequest $record): array => ['assigned_to' => $record->assigned_to])
            ->schema([
                Select::make('assigned_to')
                    ->label(__('services.admin.fields.assignee'))
                    ->placeholder(__('services.admin.actions.unassign'))
                    ->options(fn (): array => AssignServiceRequest::candidates()),
            ])
            ->action(function (ServiceRequest $record, array $data, AssignServiceRequest $assign): void {
                /** @var User $actor */
                $actor = auth()->user();
                $assignee = filled($data['assigned_to'] ?? null) ? User::find($data['assigned_to']) : null;

                try {
                    $assign->handle($actor, $record, $assignee);
                } catch (AuthorizationException|ValidationException $exception) {
                    Notification::make()->danger()->title($exception->getMessage())->send();

                    return;
                }

                Notification::make()->success()->title(__('services.admin.assigned'))->send();
            });
    }

    /**
     * زر تنزيل لكل مرفق في الطلب، لمن يملك الاطلاع عليه.
     *
     * @return list<Action>
     */
    public static function downloads(ServiceRequest $record): array
    {
        $actions = [];

        foreach ($record->opening->fields() as $field) {
            if ($field['type'] !== FormFieldType::Pdf || ! isset($record->files[$field['key']])) {
                continue;
            }

            $key = $field['key'];

            $actions[] = Action::make('download_'.$key)
                ->label(__('services.admin.actions.download', ['label' => $field['label']]))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->color('gray')
                ->authorize(fn (ServiceRequest $record): bool => auth()->user()?->can('view', $record) ?? false)
                ->action(fn (ServiceRequest $record): StreamedResponse => Storage::disk(ServiceRequest::disk())
                    ->download($record->files[$key]['path'], $record->reference.'-'.$key.'.pdf', ['Content-Type' => 'application/pdf']));
        }

        return $actions;
    }
}
