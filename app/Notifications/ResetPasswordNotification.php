<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * رسالة استعادة كلمة المرور بالعربية، تُرسل في الخلفية. الرمز مشفّر في حمولة الطابور.
 */
class ResetPasswordNotification extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.reset_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.reset_mail.line'))
            ->action(__('account.reset_mail.action'), $url)
            ->line(__('account.reset_mail.expires', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('account.reset_mail.ignore'))
            ->salutation(__('account.mail.salutation'));
    }
}
