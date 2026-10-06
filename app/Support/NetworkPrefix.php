<?php

namespace App\Support;

/**
 * بادئة الشبكة لعنوان الزائر: /24 لعناوين IPv4، و/64 لعناوين IPv6.
 * تُستعمل في حدود تحمي من استنزاف الموارد من مصدر واحد دون حجب الشبكات المشتركة كلها.
 */
final class NetworkPrefix
{
    public static function of(?string $ip): string
    {
        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);

            return $octets[0].'.'.$octets[1].'.'.$octets[2].'.0/24';
        }

        if ($ip !== null && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $packed = (string) inet_pton($ip);
            $prefix = substr($packed, 0, 8).str_repeat("\0", 8);

            return inet_ntop($prefix).'/64';
        }

        return 'unknown';
    }
}
