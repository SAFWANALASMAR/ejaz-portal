<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تنبيه المشرف العام والمسؤول التقني بتتابع رموز التحقق الثنائي الخاطئة على حساب في اليوم (WarnRepeatedTwoFactorFailures).
 * برقم الحساب وحده، بلا بريده ولا رمز ولا عنوان، والتفصيل في السجل الأمني (أقل بيانات شخصية في البريد).
 */
class TwoFactorFailuresWarning extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(
        public readonly User $account,
        public readonly int $failures,
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
            ->subject(__('account.two_factor_alert.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.two_factor_alert.line', ['failures' => $this->failures, 'account' => $this->account->id]))
            ->line(__('account.two_factor_alert.action'))
            ->salutation(__('account.mail.salutation'));
    }
}
