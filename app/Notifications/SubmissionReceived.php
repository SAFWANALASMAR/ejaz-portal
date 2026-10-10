<?php

namespace App\Notifications;

use App\Models\CompetitionSubmission;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * تأكيد استلام المشاركة برقمها المرجعي، يُرسل في الخلفية.
 */
class SubmissionReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public CompetitionSubmission $submission) {}

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
            ->subject(__('competitions.mail.subject'))
            ->greeting(__('account.mail.greeting'))
            ->line(__('competitions.mail.line', ['competition' => $this->submission->competition->title]))
            ->line(__('competitions.mail.reference', ['reference' => $this->submission->reference]))
            ->action(__('competitions.mail.action'), route('account.competitions'))
            ->salutation(__('account.mail.salutation'));
    }
}
