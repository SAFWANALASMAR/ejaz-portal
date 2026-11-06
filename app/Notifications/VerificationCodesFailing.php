<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام والمسؤول التقني: رمز تحقق فشل عند واتساب بخطأ لا تصلحه الإعادة (قالب خاطئ، أو رمز وصول منتهٍ،
 * أو حظر الرقم)، فالرموز كلها متوقفة حتى يُصلح الضبط. ومن له بريد يوثّق به في الأثناء (قرار صفوان، 6 نوفمبر 2026).
 */
class VerificationCodesFailing extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $reason) {}

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
            ->subject(__('messaging.otp_failing.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('messaging.otp_failing.line', ['reason' => $this->reason]))
            ->action(__('messaging.not_configured.action'), url('/admin/messaging-channels'))
            ->salutation(__('account.mail.salutation'));
    }
}
