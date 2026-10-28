<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام والمسؤول التقني ببلوغ المساعد الذكي سقفه الشهري، فتوقف التوليد وبقي وضع الدليل حتى الشهر التالي أو رفع السقف.
 */
class AssistantCapReached extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly float $spent,
        public readonly float $cap,
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
            ->subject(__('assistant.cap_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('assistant.cap_mail.line', ['spent' => number_format($this->spent, 2), 'cap' => number_format($this->cap, 2)]))
            ->line(__('assistant.cap_mail.action'))
            ->salutation(__('account.mail.salutation'));
    }
}
