<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه صاحب الحساب بأن كلمة مروره تغيّرت من داخل حسابه، فيكتشف جلسة مسروقة غيّرتها
 * (الفحص الأمني، 14 أكتوبر 2026). على نمط ContactDetailsChanged.
 */
class PasswordChanged extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

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
            ->subject(__('account.password_changed_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.password_changed_mail.body'))
            ->line(__('account.password_changed_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }
}
