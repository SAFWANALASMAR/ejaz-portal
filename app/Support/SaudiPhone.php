<?php

namespace App\Support;

/**
 * توحيد أرقام الجوال السعودية إلى الصيغة الدولية +9665XXXXXXXX.
 * يقبل: 05XXXXXXXX، 5XXXXXXXX، 9665XXXXXXXX، +9665XXXXXXXX، 009665XXXXXXXX،
 * بمسافات أو شرطات، وبالأرقام الهندية (٠١٢...) أو الفارسية.
 */
final class SaudiPhone
{
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = (string) preg_replace('/[\s\-().]/u', '', Digits::latin($input));
        $digits = (string) preg_replace('/^(\+|00)/', '', $digits);

        if (! ctype_digit($digits)) {
            return null;
        }

        $local = match (true) {
            str_starts_with($digits, '9665') && strlen($digits) === 12 => substr($digits, 3),
            str_starts_with($digits, '05') && strlen($digits) === 10 => substr($digits, 1),
            str_starts_with($digits, '5') && strlen($digits) === 9 => $digits,
            default => null,
        };

        return $local === null ? null : '+966'.$local;
    }

    /**
     * عرض الرقم بصيغة محلية مقروءة: 05X XXX XXXX
     */
    public static function display(?string $phone): string
    {
        if ($phone === null || ! str_starts_with($phone, '+966')) {
            return (string) $phone;
        }

        $local = '0'.substr($phone, 4);

        return substr($local, 0, 3).' '.substr($local, 3, 3).' '.substr($local, 6);
    }

    /**
     * إخفاء جزء من الرقم عند عرضه في الرسائل: 05X XXX **XX
     */
    public static function masked(?string $phone): string
    {
        $display = self::display($phone);

        return strlen($display) > 4 ? substr($display, 0, -4).'**'.substr($display, -2) : $display;
    }
}
