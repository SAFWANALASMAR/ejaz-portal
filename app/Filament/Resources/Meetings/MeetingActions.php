<?php

namespace App\Filament\Resources\Meetings;

use App\Actions\Activity\LogAccess;
use App\Actions\Assembly\ApproveMeetingMinutes;
use App\Actions\Assembly\CancelMeeting;
use App\Actions\Assembly\MarkMeetingHeld;
use App\Actions\Assembly\RecordMeetingAttendance;
use App\Actions\Assembly\UploadMeetingMinutes;
use App\Enums\ActivityEvent;
use App\Jobs\SyncOnlineMeeting;
use App\Models\Meeting;
use App\Models\User;
use App\Rules\RealPdf;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * إجراءات الاجتماع في اللوحة: الحضور، والانعقاد، والإلغاء، والمحضر رفعاً واعتماداً وتنزيلاً، والمرفقات،
 * وإعادة إنشاء رابط تيمز. كلها تستدعي إجراءات الوحدة التي تفحص الصلاحية بنفسها، وتظهر بالصلاحية نفسها
 * (MeetingPolicy). وكل تنزيل يُسجَّل.
 */
final class MeetingActions
{
    /**
     * @return list<Action>
     */
    public static function all(Meeting $meeting): array
    {
        return [
            self::recordAttendance(),
            self::markHeld(),
            self::cancel(),
            self::uploadMinutes(),
            self::approveMinutes(),
            self::downloadMinutes(),
            self::retryOnline(),
            ...self::downloads($meeting),
        ];
    }

    public static function recordAttendance(): Action
    {
        return Action::make('recordAttendance')
            ->label(__('assembly.actions.record_attendance'))
            ->icon(Heroicon::OutlinedClipboardDocumentCheck)
            ->visible(fn (Meeting $record): bool => self::actor()->can('recordAttendance', $record))
            ->fillForm(fn (Meeting $record): array => ['attendees' => $record->attendees()->pluck('users.id')->all()])
            ->schema([
                CheckboxList::make('attendees')
                    ->label(__('assembly.fields.attendees'))
                    ->options(fn (Meeting $record): array => $record->eligibleAttendees()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->bulkToggleable()
                    ->columns(2),
            ])
            ->action(fn (Meeting $record, array $data, RecordMeetingAttendance $attendance) => self::run(
                fn () => $attendance->handle(self::actor(), $record, array_values((array) ($data['attendees'] ?? []))),
                __('assembly.notices.attendance_recorded'),
            ));
    }

    public static function markHeld(): Action
    {
        return Action::make('markHeld')
            ->label(__('assembly.actions.mark_held'))
            ->icon(Heroicon::OutlinedCheckCircle)
            ->requiresConfirmation()
            ->visible(fn (Meeting $record): bool => self::actor()->can('markHeld', $record))
            ->action(fn (Meeting $record, MarkMeetingHeld $held) => self::run(fn () => $held->handle(self::actor(), $record), __('assembly.notices.held')));
    }

    public static function cancel(): Action
    {
        return Action::make('cancel')
            ->label(__('assembly.actions.cancel'))
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(__('assembly.actions.cancel_confirm'))
            ->visible(fn (Meeting $record): bool => self::actor()->can('cancel', $record))
            ->action(fn (Meeting $record, CancelMeeting $cancel) => self::run(fn () => $cancel->handle(self::actor(), $record), __('assembly.notices.cancelled')));
    }

    public static function uploadMinutes(): Action
    {
        return Action::make('uploadMinutes')
            ->label(__('assembly.actions.upload_minutes'))
            ->icon(Heroicon::OutlinedArrowUpTray)
            ->visible(fn (Meeting $record): bool => self::actor()->can('uploadMinutes', $record))
            ->schema([
                FileUpload::make('minutes_file')
                    ->label(__('assembly.fields.minutes_file'))
                    ->required()
                    ->disk(Meeting::disk())
                    ->directory(Meeting::MINUTES_DIRECTORY)
                    ->visibility('private')
                    ->acceptedFileTypes(['application/pdf'])
                    ->maxSize(20480)
                    ->rules([new RealPdf])
                    ->previewable(false),
            ])
            ->action(fn (Meeting $record, array $data, UploadMeetingMinutes $upload) => self::run(
                fn () => $upload->handle(self::actor(), $record, (string) $data['minutes_file']),
                __('assembly.notices.minutes_uploaded'),
            ));
    }

    public static function approveMinutes(): Action
    {
        return Action::make('approveMinutes')
            ->label(__('assembly.actions.approve_minutes'))
            ->icon(Heroicon::OutlinedCheckBadge)
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(__('assembly.actions.approve_minutes_confirm'))
            ->visible(fn (Meeting $record): bool => self::actor()->can('approveMinutes', $record))
            ->action(fn (Meeting $record, ApproveMeetingMinutes $approve) => self::run(fn () => $approve->handle(self::actor(), $record), __('assembly.notices.minutes_approved')));
    }

    /**
     * المحضر، مسودةً أو معتمداً، لمن يطّلع على الاجتماع في اللوحة (والمجلس يقرأ المسودة قبل اعتمادها).
     */
    public static function downloadMinutes(): Action
    {
        return Action::make('downloadMinutes')
            ->label(__('assembly.actions.download_minutes'))
            ->icon(Heroicon::OutlinedArrowDownTray)
            ->color('gray')
            ->visible(fn (Meeting $record): bool => $record->minutes_file !== null && self::actor()->can('view', $record))
            ->action(function (Meeting $record, LogAccess $log): StreamedResponse {
                $log->handle(self::actor(), $record, 'assembly', ActivityEvent::FileDownloaded, ['file' => 'minutes']);

                return Storage::disk(Meeting::disk())->download((string) $record->minutes_file, 'minutes-'.$record->starts_at->format('Y-m-d').'.pdf', ['Content-Type' => 'application/pdf']);
            });
    }

    public static function retryOnline(): Action
    {
        return Action::make('retryOnline')
            ->label(__('assembly.actions.retry_online'))
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->visible(fn (Meeting $record): bool => $record->is_online && blank($record->online_join_url) && self::actor()->can('update', $record))
            ->action(function (Meeting $record): void {
                SyncOnlineMeeting::dispatch($record->id);

                Notification::make()->success()->title(__('assembly.notices.online_retry'))->send();
            });
    }

    /**
     * زر تنزيل لكل مرفق، لمن يطّلع على الاجتماع.
     *
     * @return list<Action>
     */
    public static function downloads(Meeting $meeting): array
    {
        $actions = [];

        foreach ($meeting->attachments ?? [] as $index => $file) {
            $actions[] = Action::make('downloadAttachment'.$index)
                ->label(__('assembly.actions.download_attachment', ['name' => $file['name']]))
                ->icon(Heroicon::OutlinedPaperClip)
                ->color('gray')
                ->authorize(fn (Meeting $record): bool => self::actor()->can('view', $record))
                ->action(function (Meeting $record, LogAccess $log) use ($index): StreamedResponse {
                    $file = ($record->attachments ?? [])[$index] ?? abort(404);
                    $log->handle(self::actor(), $record, 'assembly', ActivityEvent::FileDownloaded, ['file' => 'attachment:'.$index]);

                    return Storage::disk(Meeting::disk())->download($file['path'], $file['name'], ['Content-Type' => 'application/pdf']);
                });
        }

        return $actions;
    }

    /**
     * ينفّذ الإجراء ويعرض نتيجته، وخطأ الصلاحية أو التحقق رسالةً لا صفحة خطأ.
     */
    private static function run(Closure $run, string $success): void
    {
        try {
            $run();
        } catch (AuthorizationException|ValidationException $exception) {
            Notification::make()->danger()->title($exception->getMessage())->send();

            return;
        }

        Notification::make()->success()->title($success)->send();
    }

    private static function actor(): User
    {
        /** @var User */
        return auth()->user();
    }
}
