<?php

namespace App\Jobs;

use App\Actions\Messaging\DeliverMessage;
use App\Contracts\SmsGateway;
use App\Models\MessagingChannel;
use App\Services\Messaging\MessagingException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * إرسال رسالة نصية في الخلفية. محتوى المهمة مشفّر في الطابور لأنه قد يحوي رمز تحقق. وتعيد المحاولة
 * لانقطاع المزود أو ازدحامه، ولا تعيدها لخطأ لا يصلحه التكرار (مفتاح خاطئ، أو مزود غير مضبوط).
 */
class SendSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $usage = 'otp',
    ) {}

    public function handle(SmsGateway $sms, DeliverMessage $deliver): void
    {
        try {
            $deliver->handle(MessagingChannel::SMS, $this->phone, $this->usage, fn () => $sms->send($this->phone, $this->message), $this->attempts());
        } catch (MessagingException $exception) {
            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
