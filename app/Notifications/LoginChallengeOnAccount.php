<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Cache;

/**
 * تنبيه صاحب الحساب بأن المحاولات الخاطئة على حسابه بلغت حده فأُقفل دخوله مؤقتاً، مع Turnstile مضبوطاً
 * (LoginRateLimiter، قرار صفوان 27 أكتوبر 2026). يُرسل بعد الرد، والرد على المحاولة واحد لكل معرّف، فلا يكشف وجود
 * الحساب لمن يحاول. مرة في اليوم على الأكثر، بالبريد وبالرسالة النصية متى كانت جاهزة وجواله موثقاً، كـ PasswordChanged.
 */
class LoginChallengeOnAccount extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        $sms = MessagingSettings::ready('sms') && $notifiable instanceof User && $notifiable->hasVerifiedPhone()
            && self::smsAllowed($notifiable);

        return $sms ? ['mail', SmsChannel::class] : ['mail'];
    }

    /**
     * سقفان لرسائل هذا التنبيه، وبعد أيهما بالبريد وحده:
     * - يومي للبوابة كلها، فلا يحوّل من يحل التحدي بخدمة مدفوعة على آلاف الحسابات التنبيهَ إلى إغراق رسائل وتكلفة
     *   (المراجعة الأمنية لـ #77، م-ب).
     * - أسبوعي لكل حساب، فلا يُغرق جوالَ صاحب حساب واحد من يستهدفه يوماً بعد يوم، ولا يستهلك حسابٌ واحد حصة البوابة
     *   (المراجعة المستقلة على v0.14.0).
     * ويُفحصان معاً قبل العدّ، فلا تُخصم حصة الحساب برسالة منعها سقف البوابة.
     */
    private static function smsAllowed(User $user): bool
    {
        $limits = [
            'login-challenge-sms:'.now()->toDateString() => [(int) config('security.login_challenge_sms_per_day'), 86400],
            'login-challenge-sms-account:'.$user->getKey() => [(int) config('security.login_challenge_sms_per_account_per_week'), 7 * 86400],
        ];

        foreach ($limits as $key => [$max]) {
            if ((int) Cache::get($key, 0) >= $max) {
                return false;
            }
        }

        $allowed = true;

        foreach ($limits as $key => [$max, $ttl]) {
            Cache::add($key, 0, $ttl);
            $allowed = Cache::increment($key) <= $max && $allowed;
        }

        return $allowed;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('account.login_challenge_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('account.login_challenge_mail.body'))
            ->action(__('account.login_challenge_mail.action'), route('password.request'))
            ->line(__('account.login_challenge_mail.not_you'))
            ->salutation(__('account.mail.salutation'));
    }

    public function toSms(object $notifiable): string
    {
        return __('account.login_challenge_mail.sms');
    }
}
