<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرفين العامين عند منح دور المشرف العام أو سحبه، حتى يُكتشف أي منح غير متوقع.
 */
class SuperAdminRoleChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly bool $granted,
        public readonly User $by,
    ) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $key = $this->granted ? 'granted' : 'revoked';

        return (new MailMessage)
            ->subject(__('users.super_admin_mail.'.$key.'_subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('users.super_admin_mail.'.$key.'_line', ['user' => $this->user->email, 'by' => $this->by->email]))
            ->line(__('users.super_admin_mail.check'))
            ->salutation(__('account.mail.salutation'));
    }
}
