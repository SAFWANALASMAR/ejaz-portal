<?php

namespace App\Support;

/**
 * عناوين الزوار وشبكاتها، لحدود تحمي من استنزاف الموارد من مصدر واحد
 * دون حجب الشبكات المشتركة كلها:
 * - IPv4: الشبكة /24 (شبكات الجوال والمدارس تجمع مستخدمين كثيرين خلف عنوان واحد).
 * - IPv6: المشترك /64، والموقع /48 (ما يمكن لجهة واحدة امتلاكه وتدوير عناوينه).
 * وعنوان IPv4 المغلّف بصيغة IPv6 (::ffff:a.b.c.d) يُعامل عنوان IPv4.
 */
final class NetworkPrefix
{
    /**
     * العنوان بصيغته القياسية، أو null إن لم يكن عنواناً صالحاً.
     */
    public static function normalize(?string $ip): ?string
    {
        if ($ip === null || filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return null;
        }

        $packed = (string) inet_pton($ip);

        // ::ffff:0:0/96 — عنوان IPv4 مغلّف
        if (strlen($packed) === 16 && str_starts_with($packed, str_repeat("\0", 10)."\xff\xff")) {
            return (string) inet_ntop(substr($packed, 12));
        }

        return (string) inet_ntop($packed);
    }

    public static function isIpv6(string $ip): bool
    {
        return str_contains($ip, ':');
    }

    /**
     * عنوان عام: ليس خاصاً ولا محجوزاً (الشبكة الداخلية، والحلقة المحلية...).
     */
    public static function isPublic(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
    }

    /**
     * بادئة الشبكة بطول معين: prefix('203.0.113.77', 24) = '203.0.113.0/24'.
     */
    public static function prefix(string $ip, int $bits): string
    {
        $packed = (string) inet_pton($ip);
        $bytes = intdiv($bits, 8);
        $network = substr($packed, 0, $bytes);

        if ($bits % 8 !== 0) {
            $mask = (0xFF << (8 - $bits % 8)) & 0xFF;
            $network .= chr(ord($packed[$bytes]) & $mask);
        }

        $network = str_pad($network, strlen($packed), "\0");

        return inet_ntop($network).'/'.$bits;
    }

    /**
     * العنوان مقنّعاً للسجلات: شبكته /24 في IPv4 و/48 في IPv6، فلا يُحفظ عنوان زائر بعينه.
     */
    public static function masked(?string $ip): string
    {
        $ip = self::normalize($ip);

        if ($ip === null) {
            return 'unknown';
        }

        return self::prefix($ip, self::isIpv6($ip) ? 48 : 24);
    }

    /**
     * المشترك الواحد لحد التسجيل: عنوان IPv4 كاملاً، أو /64 في IPv6.
     */
    public static function subscriber(?string $ip): string
    {
        $ip = self::normalize($ip);

        if ($ip === null) {
            return 'unknown';
        }

        return self::isIpv6($ip) ? self::prefix($ip, 64) : $ip;
    }
}
