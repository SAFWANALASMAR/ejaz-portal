<?php

namespace App\Notifications;

use App\Models\ProgramRegistration;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * بريد التسجيل في برنامج: التأكيد، أو الإدراج في قائمة الانتظار، أو الترقية منها إلى مقعد،
 * أو التذكير قبل الموعد. في الطابور بعد اعتماد المعاملة.
 */
class ProgramRegistrationChanged extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public const CONFIRMED = 'confirmed';

    public const WAITLISTED = 'waitlisted';

    public const PROMOTED = 'promoted';

    public const REMINDER = 'reminder';

    public function __construct(public ProgramRegistration $registration, public string $kind) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $program = $this->registration->program;

        return (new MailMessage)
            ->subject(__('programs.mail.'.$this->kind.'.subject', ['program' => $program->title]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('programs.mail.'.$this->kind.'.line', ['program' => $program->title]))
            ->line(__('programs.mail.when', ['date' => Dates::display($program->starts_at, withTime: true)]))
            ->action(__('programs.mail.action'), route('programs.show', $program->slug))
            ->salutation(__('account.mail.salutation'));
    }
}
