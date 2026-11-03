<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * إبلاغ صاحب الحساب بأن التحقق الثنائي لحسابه أُعيد ضبطه من اللوحة (ResetTwoFactorByAdmin)، فيكتشف إعادة ضبط لم يطلبها.
 * بلا اسم المنفّذ ولا السبب: يُطّلع عليهما في سجل العمليات.
 *
 * وفي البريد رابط تعيين كلمة المرور، فإعادة الضبط تُبطل كلمة المرور القديمة (قرار المشرف، 3 نوفمبر 2026): رابط الاستعادة
 * نفسه (المدة نفسها، ولمرة واحدة)، والرمز مشفّر في حمولة الطابور. والرسالة النصية تنبيه بلا رابط.
 */
class TwoFactorResetByAdmin extends ResetPassword implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  mixed  $notifiable
     * @return list<string>
     */
    public function via($notifiable): array
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

    /**
     * @param  string  $url
     */
    protected function buildMailMessage($url): MailMessage
    {
        return (new MailMessage)
            ->subject(__('users.reset_two_factor.mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('users.reset_two_factor.mail.line'))
            ->line(__('users.reset_two_factor.mail.password_line'))
            ->action(__('users.reset_two_factor.mail.set_password'), $url)
            ->line(__('users.reset_two_factor.mail.expires', ['count' => config('auth.passwords.users.expire')]))
            ->line(__('users.reset_two_factor.mail.next'))
            ->salutation(__('account.mail.salutation'));
    }
}
