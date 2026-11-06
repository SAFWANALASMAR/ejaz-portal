<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * رمز توثيق البريد (SendEmailVerificationCode)، يُرسل في الخلفية.
 */
class EmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public int $minutes) {}

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
            ->subject(__('account.email_code.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.email_code.line'))
            ->line('**'.$this->code.'**')
            ->line(__('account.email_code.expires', ['minutes' => $this->minutes]))
            ->line(__('account.verify_email.ignore'))
            ->salutation(__('account.mail.salutation'));
    }
}
