<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;

/**
 * ختم وقت مشفّر في النماذج العامة: الروبوت يرسل فور فتح الصفحة، والإنسان يحتاج ثواني. والختم
 * مشفّر بمفتاح التطبيق ومربوط بجلسة الزائر، فلا يُزوَّر ولا يُنقل إلى جلسة أخرى، ويُستعمل مرة واحدة.
 */
final class FormTimer
{
    public static function token(string $sessionId): string
    {
        return Crypt::encryptString(now()->getTimestamp().'|'.$sessionId);
    }

    /**
     * الثواني منذ فتح النموذج، أو null إن كان الختم مفقوداً أو معطوباً أو من جلسة أخرى.
     */
    public static function secondsSince(mixed $token, string $sessionId): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            [$issued, $session] = array_pad(explode('|', Crypt::decryptString($token), 2), 2, '');
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($issued) && hash_equals($session, $sessionId) ? now()->getTimestamp() - (int) $issued : null;
    }

    /**
     * يستهلك الختم: true أول مرة، وfalse إن استُعمل قبل ذلك (ضغطتان، أو إعادة إرسال).
     */
    public static function consume(string $token, int $seconds): bool
    {
        return Cache::add('form-timer:'.hash('sha256', $token), true, $seconds);
    }
}
