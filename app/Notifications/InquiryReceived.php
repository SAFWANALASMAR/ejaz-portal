<?php

namespace App\Notifications;

use App\Filament\Resources\Inquiries\InquiryResource;
use App\Models\Inquiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * إشعار الجمعية برسالة «تواصل معنا» على بريد التواصل في الإعدادات: الرقم والنوع ورابط اللوحة وحدها،
 * بلا أي نص من المرسل (decisions.md). وحمولة الطابور معرّف السجل لا بياناته (SerializesModels).
 */
class InquiryReceived extends Notification implements ShouldBeEncrypted, ShouldQueue, ShouldQueueAfterCommit
{
    use Queueable, SerializesModels;

    public function __construct(public Inquiry $inquiry) {}

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
            ->subject(__('inquiries.mail.received.subject', ['reference' => $this->inquiry->reference]))
            ->view('mail.inquiry-received', [
                'inquiry' => $this->inquiry,
                'url' => InquiryResource::getUrl('view', ['record' => $this->inquiry]),
            ]);
    }
}
