<?php

namespace App\Services\Messaging;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * طلب HTTP إلى مزود رسائل بمهلة محددة، وانقطاع الاتصال أو انتهاء المهلة MessagingException قابلة
 * للإعادة. ولا يُرمى استثناء العميل نفسه، ففيه الطلب كاملاً (والمفتاح في جسمه عند بعض المزودين).
 */
final class ProviderHttp
{
    public static function request(): PendingRequest
    {
        return Http::acceptJson()
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
        } catch (ConnectionException) {
            throw MessagingException::timeout();
        }
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
