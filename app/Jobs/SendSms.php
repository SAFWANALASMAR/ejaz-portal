<?php

namespace App\Jobs;

use App\Contracts\SmsGateway;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * إرسال رسالة نصية في الخلفية. محتوى المهمة مشفّر في الطابور لأنه قد يحوي رمز تحقق.
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
    ) {}

    public function handle(SmsGateway $sms): void
    {
        $sms->send($this->phone, $this->message);
    }
}
