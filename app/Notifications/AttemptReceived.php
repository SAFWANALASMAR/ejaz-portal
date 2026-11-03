<?php

namespace App\Notifications;

use App\Models\CompetitionAttempt;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تأكيد استلام محاولة في مسابقة الطلاب بالبريد، بعد التسليم يدوياً أو آلياً: المسابقة، والمرحلة ومجالها، ووقت التسليم
 * بتوقيت الرياض، ورقم المحاولة، وطريقة التسليم. ولا إجابات ولا درجة. ويُرسل في الخلفية بعد حفظ التسليم (SubmitAttempt).
 *
 * والبريد غير الموثق يصله الإيصال كما أدخله صاحبه، ومعه رابط التوثيق الموقّع نفسه (قرار صفوان، 31 أكتوبر 2026). والرابط
 * يُبنى عند الإرسال لا عند التسليم: لا يُضاف إن وُثّق البريد قبله، وتبدأ صلاحيته من خروج الرسالة.
 */
class AttemptReceived extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public CompetitionAttempt $attempt) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * عند خروج الرسالة: لا يُرسل إلى بريد غير موثق في الحساب وثّقه حساب آخر، فهو لغير الطالب يقيناً (مراجعة الفرع).
     */
    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User || $notifiable->hasVerifiedEmail()
            || ! User::query()->where('email', $notifiable->email)->whereKeyNot($notifiable->getKey())->whereNotNull('email_verified_at')->exists();
    }

    /**
     * طابور الإيصالات المستقل (config/horizon.php)، فلا تحجب دفعة إغلاق النافذة رسائل الحماية في default.
     *
     * @return array<string, string>
     */
    public function viaQueues(): array
    {
        return ['mail' => 'receipts'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $round = $this->attempt->round;
        $mail = (new MailMessage)
            ->subject(__('competitions.student.receipt_mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('competitions.student.receipt_mail.line', ['competition' => $round->competition->title]))
            ->line(__('competitions.student.receipt_mail.round', ['round' => $round->name]));

        if (filled($round->branch)) {
            $mail->line(__('competitions.student.receipt_mail.domain', ['domain' => $round->branch]));
        }

        $mail
            ->line(__('competitions.student.receipt_mail.time', ['time' => Dates::display($this->attempt->submitted_at, withTime: true)]))
            ->line(__('competitions.student.receipt_mail.reference', ['reference' => $this->attempt->reference]))
            ->line(__('competitions.student.receipt_mail.method', ['method' => __('competitions.student.receipt_mail.'.($this->attempt->auto_submitted ? 'auto' : 'manual'))]))
            ->line(__('competitions.student.receipt_mail.no_score'))
            ->action(__('competitions.mail.action'), route('account.competitions'));

        if ($notifiable instanceof User && filled($notifiable->email) && ! $notifiable->hasVerifiedEmail()) {
            $mail->line(__('competitions.student.receipt_mail.verify_line', ['minutes' => (int) config('auth.verification.expire', 60)]))
                ->line('['.__('account.verify_email.action').']('.(new VerifyEmailNotification)->urlFor($notifiable).')')
                // والبريد غير الموثق قد يكون لغير صاحب الحساب (أدخله الطالب خطأً)، فلا يوثّقه من وصله
                ->line(__('competitions.student.receipt_mail.not_you_line'));
        }

        return $mail->salutation(__('account.mail.salutation'));
    }
}
