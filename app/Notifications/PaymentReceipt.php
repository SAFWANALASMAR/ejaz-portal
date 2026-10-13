<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Support\Dates;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;

/**
 * الإيصال الإلكتروني: رقم الإيصال، والمبلغ، والغرض، والتاريخ. يُرسل في الخلفية مرة واحدة عند اعتماد
 * الدفع، إلى بريد المتبرع. العملية تُسلسَل بمعرّفها (لا ببريد المتبرع واسمه)، والحمولة مشفّرة في الطابور.
 */
class PaymentReceipt extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(public Payment $payment) {}

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
            ->subject(__('payments.mail.subject', ['receipt' => $this->payment->receipt_number]))
            ->greeting(__('account.mail.greeting'))
            ->line(__('payments.mail.thanks', ['purpose' => $this->payment->purpose->getLabel()]))
            ->line(__('payments.mail.receipt', ['receipt' => $this->payment->receipt_number]))
            ->line(__('payments.mail.amount', ['amount' => $this->payment->formattedAmount(), 'currency' => __('payments.currencies.'.$this->payment->currency)]))
            ->line(__('payments.mail.date', ['date' => Dates::display($this->payment->paid_at, withTime: true)]))
            ->action(__('payments.mail.action'), route('payments.return', $this->payment))
            ->salutation(__('account.mail.salutation'));
    }
}
