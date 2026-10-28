<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام والمسؤول التقني قبل بلوغ سقف رسائل التحقق في الساعة، ليرفعه قبل أن يتوقف التسجيل.
 */
class SmsCapWarning extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly int $percent,
        public readonly int $sent,
        public readonly int $cap,
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
        return (new MailMessage)
            ->subject(__('account.sms_cap.subject', ['percent' => $this->percent]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.sms_cap.line', [
                'sent' => number_format($this->sent),
                'cap' => number_format($this->cap),
                'percent' => $this->percent,
            ]))
            ->line(__('account.sms_cap.action'))
            ->salutation(__('account.mail.salutation'));
    }
}
