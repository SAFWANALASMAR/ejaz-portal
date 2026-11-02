<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط اجتماع يلصقه السكرتير يدوياً: https بلا اسم مستخدم ولا منفذ (SafeUrl)، ومن نطاق معتمد وحده:
 * تيمز وزووم (ونطاقاته الفرعية مثل us02web.zoom.us) وجوجل ميت. والمطابقة للنطاق كاملاً أو لنهايته بعد نقطة،
 * فلا يمر zoom.us.evil.com ولا evilzoom.us.
 */
class MeetingJoinUrl implements ValidationRule
{
    /** طول معقول لرابط اجتماع، والعمود نص مشفّر */
    public const MAX_LENGTH = 2048;

    private const HOSTS = ['teams.microsoft.com', 'teams.live.com', 'zoom.us'];

    private const MEET_HOST = 'meet.google.com';

    /** نطاقات تُقبل نطاقاتها الفرعية */
    private const SUBDOMAIN_HOSTS = ['zoom.us'];

    public static function isValid(?string $url): bool
    {
        if (mb_strlen((string) $url) > self::MAX_LENGTH || ! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $parts = (array) parse_url((string) $url);

        if (isset($parts['port'])) {
            return false;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));

        // جوجل ميت برمز الاجتماع وحده، فلا يمر مسار تحويل مثل /linkredirect?dest=
        if ($host === self::MEET_HOST) {
            return preg_match('#^/[a-z]{3}-[a-z]{4}-[a-z]{3}$#', (string) ($parts['path'] ?? '')) === 1;
        }

        if (in_array($host, self::HOSTS, true)) {
            return true;
        }

        // نطاق فرعي بمستوى واحد (us02web.zoom.us)
        foreach (self::SUBDOMAIN_HOSTS as $domain) {
            if (preg_match('/^[a-z0-9-]+\.'.preg_quote($domain, '/').'$/', $host) === 1) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('validation.meeting_join_url')->translate();
        }
    }
}
