<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط اجتماع يلصقه السكرتير يدوياً: https بلا اسم مستخدم ولا منفذ (SafeUrl)، ومن نطاق معتمد وحده، وبمسار
 * انضمام إلى اجتماع وحده، فلا يمر رابط تحويل أو تشغيل (/l/launcher، /launch، /linkredirect):
 * - teams.microsoft.com تحت /l/meetup-join/ أو /meet/، وteams.live.com تحت /meet/.
 * - zoom.us ونطاق فرعي له بمستوى واحد (us02web.zoom.us) تحت /j/ أو /w/ أو /my/.
 * - meet.google.com برمز الاجتماع وحده (/abc-defg-hij).
 * والنطاق مطابق كاملاً، فلا يمر zoom.us.evil.com ولا evilzoom.us. ولا مقطع «.» أو «..» في المسار، صريحاً
 * أو مرمّزاً، فلا يخرج المتصفح من مسار الانضمام بتطبيعه.
 */
class MeetingJoinUrl implements ValidationRule
{
    /** طول معقول لرابط اجتماع، والعمود نص مشفّر */
    public const MAX_LENGTH = 2048;

    /** مسار الانضمام المسموح لكل نطاق */
    private const PATHS = [
        'teams.microsoft.com' => '#^/(l/meetup-join|meet)/[^/]#',
        'teams.live.com' => '#^/meet/[^/]#',
        'zoom.us' => '#^/(j|w|my)/[^/]#',
        'meet.google.com' => '#^/[a-z]{3}-[a-z]{4}-[a-z]{3}$#',
    ];

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
        $path = (string) ($parts['path'] ?? '');

        // نطاق زووم الفرعي بمستوى واحد يأخذ مسارات zoom.us
        if (preg_match('/^[a-z0-9-]+\.zoom\.us$/', $host) === 1) {
            $host = 'zoom.us';
        }

        if (! isset(self::PATHS[$host]) || preg_match('#(^|/)\.{1,2}(/|$)#', rawurldecode($path)) === 1) {
            return false;
        }

        return preg_match(self::PATHS[$host], $path) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('validation.meeting_join_url')->translate();
        }
    }
}
