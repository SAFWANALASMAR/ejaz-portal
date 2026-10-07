<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار المتقدم بتغيّر حالة طلبه، ومعه الرد إن كتبه الموظف. ولا تصله الملاحظة الداخلية.
 * في الطابور بعد اعتماد المعاملة، والحمولة مشفّرة لأن الرد قد يحمل بيانات شخصية.
 */
class ServiceRequestStatusChanged extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use Queueable;

    public function __construct(public ServiceRequest $request, public ServiceRequestEvent $event) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject(__('services.mail.subject', ['reference' => $this->request->reference]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('services.mail.line', [
                'reference' => $this->request->reference,
                'opening' => $this->request->opening->title,
                'status' => $this->event->to_status->getLabel(),
            ]));

        if (filled($this->event->message)) {
            $mail->line(__('services.mail.message'))->line((string) $this->event->message);
        }

        return $mail
            ->action(__('services.mail.action'), route('account.requests.show', $this->request))
            ->salutation(__('account.mail.salutation'));
    }
}
