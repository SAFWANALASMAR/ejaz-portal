<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه صاحب حساب بأن أحداً حاول إنشاء حساب ببريده (CreateNewUser). فالتسجيل يرد رسالة عامة لا تكشف أن البريد مسجل،
 * وصاحبه يعرف بالمحاولة، ويجد طريق الدخول والاستعادة إن كان هو (قرار صفوان، 20 أكتوبر 2026). مرة في اليوم على الأكثر.
 */
class RegistrationAttemptOnAccount extends Notification implements ShouldQueueAfterCommit
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
            ->subject(__('account.register_attempt_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.register_attempt_mail.body'))
            ->action(__('account.register_attempt_mail.action'), route('password.request'))
            ->line(__('account.register_attempt_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }
}
