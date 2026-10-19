<?php

namespace App\Jobs;

use App\Actions\Accounts\SendPhoneVerificationCode;
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
 *
 * ورمز التحقق (otp) خاصة:
 * - لا يُعاد بعد مهلة قراءة: ربما وصل، وإعادته تكرره وتضاعف تكلفته. ويطلب المستخدم رمزاً جديداً إن لم يصله.
 * - ولا يُرسل بعد انتهاء عمره (عشر دقائق من طلبه)، ولو تأخر الطابور أو تكررت المحاولات.
 */
class SendSms implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [10, 30];

    // وقت طلب الرسالة (ثوانٍ منذ 1970)، لمعرفة عمر رمز التحقق عند كل محاولة
    public int $queuedAt;

    public function __construct(
        public readonly string $phone,
        public readonly string $message,
        public readonly string $usage = 'otp',
    ) {
        $this->queuedAt = now()->getTimestamp();
    }

    public function handle(SmsGateway $sms, DeliverMessage $deliver): void
    {
        if ($this->usage === 'otp' && now()->getTimestamp() - $this->queuedAt > SendPhoneVerificationCode::TTL_MINUTES * 60) {
            $deliver->skip(MessagingChannel::SMS, $this->phone, $this->usage, 'expired', $this->attempts());

            return;
        }

        try {
            $deliver->handle(MessagingChannel::SMS, $this->phone, $this->usage, fn () => $sms->send($this->phone, $this->message), $this->attempts());
        } catch (MessagingException $exception) {
            if (! $exception->retryable || ($exception->outcomeUnknown && $this->usage === 'otp')) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }
}
