<?php

namespace App\Notifications;

use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام والمسؤول التقني عند منح أحد دوريهما أو سحبه، حتى يُكتشف أي منح غير متوقع.
 * يدخل الطابور بعد اعتماد المعاملة أياً كان الاتصال، فلا يلتقطه عامل قبل حفظ الحساب.
 */
class SuperAdminRoleChanged extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly User $user,
        public readonly bool $granted,
        public readonly ?User $by,
        public readonly RoleName $role = RoleName::SuperAdmin,
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
        $key = $this->granted ? 'granted' : 'revoked';

        return (new MailMessage)
            ->subject(__('users.super_admin_mail.'.$key.'_subject', ['role' => $this->role->getLabel()]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('users.super_admin_mail.'.$key.'_line', ['user' => $this->user->identifier(), 'by' => $this->by?->identifier() ?? __('users.super_admin_mail.console'), 'role' => $this->role->getLabel()]))
            ->line(__('users.super_admin_mail.check'))
            ->salutation(__('account.mail.salutation'));
    }
}
