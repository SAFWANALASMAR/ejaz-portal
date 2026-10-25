<?php

namespace App\Support;

/**
 * العنوان الوطني المختصر (أربعة أحرف لاتينية وأربعة أرقام، مثل RRRD2929) لخريطة المقر في «تواصل معنا» (ملاحظات المدير
 * التنفيذي، 25 أكتوبر 2026). يولّد رابط بحث عنه في خرائط جوجل ما لم يُدخل رابط الخريطة صراحة. ولا رابط رسمياً موثقاً
 * لفتح العنوان المختصر مباشرة، فيُتحقق من الرابط المولّد عند وصول العنوان (open-questions).
 */
final class NationalAddress
{
    public const PATTERN = '/^[A-Z]{4}[0-9]{4}$/';

    public static function normalize(?string $code): ?string
    {
        $code = strtoupper((string) preg_replace('/\s+/u', '', Digits::latin((string) $code)));

        return preg_match(self::PATTERN, $code) === 1 ? $code : null;
    }

    public static function mapUrl(?string $code): ?string
    {
        $code = self::normalize($code);

        return $code === null ? null : 'https://www.google.com/maps/search/?api=1&query='.rawurlencode($code);
    }
}
