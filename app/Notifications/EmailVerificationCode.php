<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * رمز توثيق البريد (SendEmailVerificationCode)، يُرسل في الخلفية.
 */
class EmailVerificationCode extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * ثلاث محاولات: خنق SMTP في مايكروسوفت 365 عابر، والمشرف supervisor-1 بمحاولة واحدة (docs/decisions/2026-11-06-deploy-readiness.md).
     */
    public int $tries = 3;

    public function __construct(public string $code, public int $minutes) {}

    /**
     * الانتظار قبل كل إعادة، والرمز صالح عشر دقائق فلا تطول.
     *
     * @return list<int>
     */
    public function backoff(): array
    {
        return [30, 60];
    }

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
            ->subject(__('account.email_code.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.email_code.line'))
            ->line('**'.$this->code.'**')
            ->line(__('account.email_code.expires', ['minutes' => $this->minutes]))
            ->line(__('account.verify_email.ignore'))
            ->salutation(__('account.mail.salutation'));
    }
}
