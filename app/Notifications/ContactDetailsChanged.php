<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه البريد المسجّل قبل التغيير بأن بريد الحساب أو جواله تغيّر، فيكتشف صاحبه جلسة مسروقة غيّرته
 * (docs/security/2026-10-12-security-audit.md). يُرسل إلى البريد القديم، ولا يُذكر فيه الجديد.
 */
class ContactDetailsChanged extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly bool $emailChanged,
        public readonly bool $phoneChanged,
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
        $what = match (true) {
            $this->emailChanged && $this->phoneChanged => 'both',
            $this->emailChanged => 'email',
            default => 'phone',
        };

        return (new MailMessage)
            ->subject(__('account.contact_changed_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.contact_changed_mail.'.$what))
            ->line(__('account.contact_changed_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }
}
