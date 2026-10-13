<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تأكيد استلام رسالة «تواصل معنا» للمرسل: رقمها وحده، بلا أي نص من مدخلاته (الاسم والموضوع
 * والرسالة). فلا يُستغل النموذج لإرسال نص إلى بريد غيره، والحد اليومي لكل بريد يمنع الإغراق.
 */
class InquiryConfirmation extends Notification implements ShouldBeEncrypted, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public string $reference) {}

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
            ->subject(__('inquiries.mail.confirmation.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('inquiries.mail.confirmation.line'))
            ->line(__('inquiries.mail.confirmation.reference', ['reference' => $this->reference]))
            ->line(__('inquiries.mail.confirmation.unexpected'))
            ->salutation(__('account.mail.salutation'));
    }
}
