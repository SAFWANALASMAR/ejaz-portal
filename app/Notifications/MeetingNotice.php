<?php

namespace App\Notifications;

use App\Models\Meeting;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * دعوة عضو الجمعية العمومية إلى اجتماع، أو إبلاغه بتعديل موعده أو مكانه، أو بإلغائه (requirements/04).
 * فيها رابط صفحة الاجتماع في البوابة لا رابط تيمز، فلا يظهر الرابط إلا للأعضاء بعد الدخول.
 * في الطابور بعد اعتماد المعاملة.
 */
class MeetingNotice extends Notification implements ShouldQueueAfterCommit
{
    use Queueable;

    /**
     * @param  'scheduled'|'updated'|'cancelled'  $kind
     */
    public function __construct(public Meeting $meeting, public string $kind) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $meeting = $this->meeting;
        $type = $meeting->type->getLabel();

        $mail = (new MailMessage)
            ->subject(__('assembly.mail.'.$this->kind.'_subject', ['title' => $meeting->title]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('assembly.mail.'.$this->kind.'_line', ['type' => $type, 'title' => $meeting->title]))
            ->line(__('assembly.mail.when', ['when' => Dates::display($meeting->starts_at, withTime: true)]));

        if ($this->kind !== 'cancelled') {
            if (filled($meeting->location)) {
                $mail->line(__('assembly.mail.where', ['where' => $meeting->location]));
            }

            if ($meeting->is_online) {
                $mail->line(__('assembly.mail.online'));
            }
        }

        return $mail
            ->action(__('assembly.mail.action'), route('assembly.meetings.show', $meeting))
            ->salutation(__('account.mail.salutation'));
    }
}
