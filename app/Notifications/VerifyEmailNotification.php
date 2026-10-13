<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * رسالة توثيق البريد بالعربية، تُرسل في الخلفية.
 */
class VerifyEmailNotification extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.verify_email.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.verify_email.line'))
            ->action(__('account.verify_email.action'), $url)
            ->line(__('account.verify_email.ignore'))
            ->salutation(__('account.mail.salutation'));
    }
}
