<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه صاحب الحساب بأن كلمة مروره تغيّرت، من داخل حسابه أو بالاستعادة، فيكتشف جلسة مسروقة غيّرتها (الفحص الأمني،
 * 14 أكتوبر 2026). بالبريد، وبالرسالة النصية متى كانت جاهزة وجواله موثقاً (قرار صفوان، 20 أكتوبر 2026).
 */
class PasswordChanged extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $sms = MessagingSettings::ready('sms') && $notifiable instanceof User && $notifiable->hasVerifiedPhone();

        return $sms ? ['mail', SmsChannel::class] : ['mail'];
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

    public function toSms(object $notifiable): string
    {
        return __('account.password_changed_mail.sms');
    }
}
