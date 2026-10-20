<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام ببلوغ سقف التسجيل في الساعة (CreateNewUser)، فيتوقف التسجيل حتى تنقضي الساعة. مرة في الساعة، على
 * نمط SmsCapWarning.
 */
class RegistrationCapReached extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly int $cap) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.register_cap.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.register_cap.line', ['cap' => number_format($this->cap)]))
            ->line(__('account.register_cap.action'))
            ->salutation(__('account.mail.salutation'));
    }
}
