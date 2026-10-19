<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use RuntimeException;

/**
 * تعذّر المزود: مهلة، أو خطأ اتصال، أو رد بخطأ أو بلا نص. الزائر يرى «المساعد مشغول»، والسبب في سجل التطبيق
 * بلا السؤال ولا المفتاح.
 *
 * وانتهاء المهلة يعني أن الطلب وصل وقد يحاسب عليه المزود، فيُحسب على السقف تقديراً ($timedOut — مراجعة المشرف، ٣).
 */
class AssistantProviderException extends RuntimeException
{
    public bool $timedOut = false;

    public static function connection(string $provider, ConnectionException $previous): self
    {
        $exception = new self($provider.' request failed or timed out.', 0, $previous);
        $exception->timedOut = preg_match('/timed out|cURL error 28/i', $previous->getMessage()) === 1;

        return $exception;
    }
}
