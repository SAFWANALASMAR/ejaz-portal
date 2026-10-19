<?php

namespace App\Services\Messaging;

use App\Models\MessageLog;
use RuntimeException;

/**
 * فشل إرسال رسالة عند المزود. السبب رمز قصير يُحفظ في سجل الرسائل (مثل http_401 أو timeout أو
 * unifonic:ER-04)، ولا يحمل المفتاح ولا نص الرسالة ولا الرقم. وretryable: هل تفيد إعادة المحاولة في الطابور.
 * وlog: سجل الحالة الذي كتبه DeliverMessage لهذا الفشل.
 */
class MessagingException extends RuntimeException
{
    // طول عمود error في message_logs
    private const MAX_REASON = 100;

    public readonly string $reason;

    public ?MessageLog $log = null;

    /**
     * @param  bool  $outcomeUnknown  انقطع الاتصال بعد وصول الطلب: ربما أُرسلت الرسالة (رمز التحقق لا يُعاد)
     */
    public function __construct(string $reason, public readonly bool $retryable = false, public readonly bool $outcomeUnknown = false)
    {
        // السبب قد يأتي من رد المزود، فيُقص فلا يرفضه عمود السجل
        $this->reason = mb_substr($reason, 0, self::MAX_REASON);

        parent::__construct('Messaging provider error: '.$this->reason);
    }

    public static function notConfigured(): self
    {
        return new self('not_configured');
    }

    /**
     * لم يصل الطلب إلى المزود (الاسم أو الاتصال أو مهلته): الإعادة آمنة.
     */
    public static function connectFailed(): self
    {
        return new self('connect_failed', retryable: true);
    }

    /**
     * انتهت مهلة القراءة بعد الاتصال: النتيجة مجهولة. تُعاد للإشعارات، ولا تُعاد لرمز التحقق (SendSms).
     */
    public static function outcomeUnknown(): self
    {
        return new self('timeout_unknown', retryable: true, outcomeUnknown: true);
    }

    /**
     * رد HTTP غير ناجح: المهلة والازدحام وأخطاء الخادم تُعاد، وأخطاء الطلب والمفتاح لا تُعاد.
     */
    public static function http(int $status, ?string $code = null): self
    {
        return new self($code !== null && $code !== '' ? $code : 'http_'.$status, retryable: $status === 429 || $status >= 500);
    }
}
