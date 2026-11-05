<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه صاحب حساب نُزع منه رقم جوال غير موثق: وثّقه صاحبه بالرمز، أو حرّره مدير النظام (ReleasePhone). يُرسل لمن له بريد
 * موثق وحده (مراجعة المشرف على #180، المهمة ١ — decisions/2026-11-05-phone-login.md).
 */
class PhoneNumberReleased extends Notification implements ShouldQueueAfterCommit
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
            ->subject(__('account.phone_released_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.phone_released_mail.body'))
            ->action(__('account.phone_released_mail.action'), route('login'))
            ->line(__('account.phone_released_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }
}
