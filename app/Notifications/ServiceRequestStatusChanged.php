<?php

namespace App\Notifications;

use App\Models\ServiceRequest;
use App\Models\ServiceRequestEvent;
use App\Models\User;
use App\Notifications\Channels\SmsChannel;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * إشعار المتقدم بتغيّر حالة طلبه بالبريد، ومعه الرد إن كتبه الموظف، وبرسالة نصية إن فُعّلت
 * (SMS_REQUEST_UPDATES). ولا تصله الملاحظة الداخلية.
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
        // الرسالة النصية «حسب الإعداد» (requirements/02)، ولجوال موثق فقط
        $sms = MessagingSettings::usageEnabled('sms', 'request_updates') && $notifiable instanceof User && $notifiable->hasVerifiedPhone();

        return $sms ? ['mail', SmsChannel::class] : ['mail'];
    }

    /**
     * نص قصير بلا رد الموظف، فالرد قد يطول ويحمل تفاصيل لا تناسب الرسالة النصية.
     */
    public function toSms(object $notifiable): string
    {
        return __('services.sms', [
            'reference' => $this->request->reference,
            'status' => $this->event->to_status->getLabel(),
            'url' => route('account.requests.show', $this->request),
        ]);
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
