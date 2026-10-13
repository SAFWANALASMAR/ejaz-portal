<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار صاحب الحساب بمحاولة استبدال سر تحققه الثنائي المؤكد من جلسته (رُفضت، الفحص الأمني F8): من أرسلها
 * يملك الجلسة وكلمة المرور، فإن لم يكن صاحبها فالحساب مخترق.
 */
class TwoFactorRotationRefused extends Notification implements ShouldQueueAfterCommit
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
            ->subject(__('account.two_factor_rotation.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.two_factor_rotation.line'))
            ->line(__('account.two_factor_rotation.action'))
            ->salutation(__('account.mail.salutation'));
    }
}
