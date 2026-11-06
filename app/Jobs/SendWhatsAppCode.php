<?php

namespace App\Jobs;

use App\Actions\Accounts\SendPhoneVerificationCode;
use App\Actions\Messaging\DeliverMessage;
use App\Contracts\WhatsAppNotifier;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingException;
use App\Services\WhatsApp\WhatsAppMessage;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * رمز التحقق عبر واتساب في الخلفية (قرار صفوان، 6 نوفمبر 2026)، بقالب «المصادقة» المعتمد عند Meta: متغير نصه الرمز،
 * وزر «نسخ الرمز» يحمله. كرمز SendSms: المهمة مشفّرة في الطابور، ولا تُعاد بعد مهلة قراءة (ربما وصل)، ولا تُرسل بعد عمر
 * الرمز. ومن لا واتساب له يوثّق ببريده (User::isVerified بالبريد أو الجوال).
 */
class SendWhatsAppCode implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    // وقت طلب الرمز (ثوانٍ منذ 1970)، لمعرفة عمره عند كل محاولة
    public int $queuedAt;

    public function __construct(
        public readonly string $phone,
        public readonly string $code,
        public readonly string $text,
    ) {
        $this->queuedAt = now()->getTimestamp();
    }

    public function handle(WhatsAppNotifier $whatsapp, DeliverMessage $deliver): void
    {
        if (now()->getTimestamp() - $this->queuedAt > SendPhoneVerificationCode::TTL_MINUTES * 60) {
            $deliver->skip(MessagingChannel::WHATSAPP, $this->phone, 'otp', 'expired', $this->attempts());

            return;
        }

        $message = new WhatsAppMessage('otp', [$this->code], $this->text, copyCode: $this->code);

        try {
            $deliver->handle(MessagingChannel::WHATSAPP, $this->phone, 'otp', fn () => $whatsapp->send($this->phone, $message), $this->attempts());
        } catch (MessagingException $exception) {
            if (! $exception->retryable || $exception->outcomeUnknown) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
