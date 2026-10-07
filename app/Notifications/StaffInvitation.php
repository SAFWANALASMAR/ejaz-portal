<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * دعوة صاحب حساب أنشأته الإدارة إلى تعيين كلمة مروره. رابطها رابط الاستعادة نفسه
 * (المدة نفسها، ولمرة واحدة)، بنص يوضح أنها دعوة لا طلب استعادة.
 */
class StaffInvitation extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users.invitation.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('users.invitation.line'))
            ->action(__('users.invitation.action'), $url)
            ->line(__('users.invitation.expires', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('users.invitation.unexpected'))
            ->salutation(__('account.mail.salutation'));
    }
}
