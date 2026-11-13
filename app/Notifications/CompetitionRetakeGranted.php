<?php

namespace App\Notifications;

use App\Models\CompetitionRetake;
use App\Models\User;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * «أُتيحت لك إعادة الاختبار» بالبريد (GrantCompetitionRetake): المسابقة والمرحلة، والمهلة إن حُددت، ورابط «حسابي»
 * حيث بطاقة البدء. ولا سبب ولا درجة. ولا يُرسل إلى بريد غير موثق وثّقه حساب آخر، كإيصال المحاولة (AttemptReceived).
 */
class CompetitionRetakeGranted extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public CompetitionRetake $retake) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && filled($notifiable->email) ? ['mail'] : [];
    }

    public function shouldSend(object $notifiable, string $channel): bool
    {
        return ! $notifiable instanceof User || $notifiable->hasVerifiedEmail()
            || ! User::query()->where('email', $notifiable->email)->whereKeyNot($notifiable->getKey())->whereNotNull('email_verified_at')->exists();
    }

    public function toMail(object $notifiable): MailMessage
    {
        $round = $this->retake->round;
        $mail = (new MailMessage)
            ->subject(__('competitions.student.retake.mail_subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('competitions.student.retake.mail_line', ['competition' => $round->competition->title, 'round' => $round->name]));

        $mail->line($this->retake->retake_until !== null
            ? __('competitions.student.retake.mail_until', ['time' => Dates::display($this->retake->retake_until, withTime: true)])
            : __('competitions.student.retake.mail_window', ['time' => Dates::display($round->ends_at, withTime: true)]));

        return $mail->action(__('competitions.mail.action'), route('account.competitions'))
            ->salutation(__('account.mail.salutation'));
    }
}
