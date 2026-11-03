<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إبلاغ صاحب الحساب بأن التحقق الثنائي لحسابه أُعيد ضبطه من اللوحة (ResetTwoFactorByAdmin)، فيكتشف إعادة ضبط لم يطلبها.
 * بلا اسم المنفّذ ولا السبب: يُطّلع عليهما في سجل العمليات.
 */
class TwoFactorResetByAdmin extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        // البريد الموثق وحده، فغير الموثق قد يحمله حساب آخر. والجوال الموثق متى كانت الرسائل جاهزة، فلا يفوت التنبيه
        // حساباً بلا بريد (على نمط PasswordChanged)
        $user = $notifiable instanceof User ? $notifiable : null;

        return array_values(array_filter([
            $user?->hasVerifiedEmail() ? 'mail' : null,
            $user?->hasVerifiedPhone() && MessagingSettings::ready('sms') ? SmsChannel::class : null,
        ]));
    }

    public function toSms(object $notifiable): string
    {
        return __('users.reset_two_factor.mail.sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users.reset_two_factor.mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('users.reset_two_factor.mail.line'))
            ->line(__('users.reset_two_factor.mail.next'))
            ->action(__('users.reset_two_factor.mail.contact'), route('contact'))
            ->salutation(__('account.mail.salutation'));
    }
}
