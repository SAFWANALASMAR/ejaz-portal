<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام: رسالة لم تُرسل في الإنتاج لأن مزود قناتها غير مضبوط (رموز التحقق مثلاً).
 */
class MessagingNotConfigured extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public readonly string $channel) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $channel = __('messaging.channels.'.$this->channel);

        return (new MailMessage)
            ->subject(__('messaging.not_configured.subject', ['channel' => $channel]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('messaging.not_configured.line', ['channel' => $channel]))
            ->action(__('messaging.not_configured.action'), url('/admin/messaging-channels'))
            ->salutation(__('account.mail.salutation'));
    }
}
