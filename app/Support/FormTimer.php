<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;

/**
 * ختم وقت مشفّر في النماذج العامة: الروبوت يرسل فور فتح الصفحة، والإنسان يحتاج ثواني. والختم
 * مشفّر بمفتاح التطبيق، فلا يُزوَّر ولا يُعاد ضبطه.
 */
final class FormTimer
{
    public static function token(): string
    {
        return Crypt::encryptString((string) now()->getTimestamp());
    }

    /**
     * الثواني منذ فتح النموذج، أو null إن كان الختم مفقوداً أو معطوباً.
     */
    public static function secondsSince(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            $issued = Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }

        return ctype_digit($issued) ? now()->getTimestamp() - (int) $issued : null;
    }
}
