<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط مقطع المكتبة المرئية (requirements/01 — قسم 5: «يوتيوب أو مستضاف»):
 * - رابط يوتيوب بأشكاله (watch، وyoutu.be، وembed، وshorts، وlive)، ويُعرض من youtube-nocookie.com.
 * - أو رابط ملف فيديو (mp4 أو webm) من نطاق تخزين معتمد في سياسة أمان المحتوى (CSP_MEDIA_SRC)،
 *   فيعمل في مشغّل المتصفح ولا تمنعه السياسة.
 * وكلاهما https بلا اسم مستخدم (SafeUrl).
 */
class VideoUrl implements ValidationRule
{
    /** نمط معرّف مقطع يوتيوب، ويستعمله ما يبني من المعرّف مسار ملف (FetchHomeVideoThumbnail) */
    public const YOUTUBE_ID = '/^[A-Za-z0-9_-]{11}$/';

    private const YOUTUBE_HOSTS = ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'www.youtube-nocookie.com'];

    private const FILE_EXTENSIONS = ['mp4', 'webm'];

    /**
     * طول العمود في القاعدة.
     */
    public const MAX_LENGTH = 500;

    /**
     * @param  bool  $youtubeOnly  يوتيوب وحده، لموضع لا يعرض إلا مقاطع يوتيوب (مقطع الرئيسية)
     */
    public function __construct(private bool $youtubeOnly = false) {}

    public static function isValid(?string $url): bool
    {
        return mb_strlen((string) $url) <= self::MAX_LENGTH && (self::youtubeId($url) !== null || self::isHostedFile($url));
    }

    /**
     * معرّف مقطع يوتيوب من رابطه، أو null إن لم يكن رابط يوتيوب صالحاً.
     */
    public static function youtubeId(?string $url): ?string
    {
        if (! SafeUrl::isSafe($url, allowInternal: false)) {
            return null;
        }

        $parts = (array) parse_url((string) $url);
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = (string) ($parts['path'] ?? '');
        parse_str((string) ($parts['query'] ?? ''), $query);

        $id = match (true) {
            $host === 'youtu.be' => substr($path, 1),
            ! in_array($host, self::YOUTUBE_HOSTS, true) => null,
            $path === '/watch' => $query['v'] ?? null,
            preg_match('#^/(?:embed|shorts|live)/([^/]+)/?$#', $path, $match) === 1 => $match[1],
            default => null,
        };

        return is_string($id) && preg_match(self::YOUTUBE_ID, $id) === 1 ? $id : null;
    }

    /**
     * رابط ملف فيديو من نطاق معتمد في media-src.
     */
    public static function isHostedFile(?string $url): bool
    {
        if (! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $parts = (array) parse_url((string) $url);
        $origin = 'https://'.strtolower((string) ($parts['host'] ?? '')).(isset($parts['port']) ? ':'.$parts['port'] : '');
        $extension = strtolower(pathinfo((string) ($parts['path'] ?? ''), PATHINFO_EXTENSION));

        /** @var list<string> $allowed */
        $allowed = config('security.csp.media_src');
        $allowed = array_map(fn (string $source): string => strtolower(rtrim(trim($source), '/')), $allowed);

        return in_array($extension, self::FILE_EXTENSIONS, true) && in_array($origin, $allowed, true);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('validation.video_url')->translate();

            return;
        }

        if ($this->youtubeOnly && self::youtubeId($value) === null) {
            $fail('validation.youtube_url')->translate();
        }
    }
}
