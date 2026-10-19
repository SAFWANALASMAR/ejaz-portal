<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * طلب HTTP إلى مزود رسائل بمهلة محددة. ولا يُرمى استثناء العميل نفسه، ففيه الطلب كاملاً (والمفتاح في
 * جسمه عند بعض المزودين). والانقطاع نوعان:
 * - في مرحلة الاتصال (تعذّر حل الاسم أو الاتصال أو انتهت مهلته): الطلب لم يصل، فتُعاد المحاولة بأمان.
 * - بعد الاتصال (مهلة القراءة): ربما قبل المزود الرسالة وأرسلها، فالنتيجة مجهولة، وإعادتها قد تكرر الرسالة.
 */
final class ProviderHttp
{
    public static function request(): PendingRequest
    {
        // بلا تتبّع للتحويل: المفتاح في جسم الطلب عند بعض المزودين، فلا يُعاد إرساله إلى عنوان آخر (اختبار الأمن، 19 أكتوبر)
        return Http::acceptJson()
            ->withoutRedirecting()
            ->timeout((int) config('messaging.timeout'))
            ->connectTimeout((int) config('messaging.connect_timeout'));
    }

    /**
     * @param  callable(): Response  $send
     */
    public static function send(callable $send): Response
    {
        try {
            return $send();
        } catch (ConnectionException $exception) {
            throw self::beforeRequest($exception) ? MessagingException::connectFailed() : MessagingException::outcomeUnknown();
        }
    }

    /**
     * هل انقطع الاتصال قبل أن يصل الطلب؟ من رمز cURL في أول رسالة الاستثناء (Guzzle يكتبه «cURL error N:»):
     * 6 تعذّر حل الاسم، و7 تعذّر الاتصال، و28 للمهلتين معاً («Connection/Resolving timed out» للاتصال،
     * و«Operation timed out» للقراءة). وما سواها مجهول النتيجة.
     */
    private static function beforeRequest(ConnectionException $exception): bool
    {
        return preg_match('/cURL error (6|7):|cURL error 28: (Connection|Resolving) timed out/i', $exception->getMessage()) === 1;
    }

    /**
     * الرقم الدولي بلا + ولا 00 (صيغة المزودين السعوديين الثلاثة).
     */
    public static function digits(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }
}
