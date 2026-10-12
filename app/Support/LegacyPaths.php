<?php

namespace App\Support;

/**
 * أقرب قسم في البوابة لرابط ووردبريس قديم لا تحويل مسجلاً له (FallbackController)، فلا يصل
 * رابط مفهرس من الموقع السابق إلى صفحة 404. وروابط لوحة ووردبريس (wp-admin وwp-login.php) ليست
 * روابط عامة، فتبقى 404.
 */
final class LegacyPaths
{
    /**
     * معاملات الاستعلام التي كان ووردبريس يعرض بها المحتوى على الرئيسية (/?p=)، وقسم كل منها.
     */
    public const QUERY_KEYS = [
        'p' => '/media/news',
        'cat' => '/media/news',
        'tag' => '/media/news',
        'feed' => '/media/news',
        'm' => '/media/news',
        'page_id' => '/',
        'attachment_id' => '/',
        'author' => '/',
        's' => '/',
    ];

    private const UPLOAD_SECTIONS = [
        'pdf' => '/governance',
        'mp3' => '/library/audio',
        'm4a' => '/library/audio',
        'wav' => '/library/audio',
        'ogg' => '/library/audio',
        'mp4' => '/library/videos',
    ];

    /**
     * القسم الأقرب لمسار قديم، أو null إن لم يكن من مسارات ووردبريس.
     */
    public static function nearest(string $path): ?string
    {
        $path = RedirectMap::normalize($path);

        if (str_starts_with($path, 'wp-content/uploads/')) {
            return self::UPLOAD_SECTIONS[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? '/';
        }

        return match (true) {
            str_starts_with($path, 'wp-content/'), str_starts_with($path, 'wp-includes/'), str_starts_with($path, 'wp-json'),
            $path === 'index.php', preg_match('#^page/\d+$#', $path) === 1 => '/',
            $path === 'feed', str_ends_with($path, '/feed'), str_starts_with($path, 'category/'), str_starts_with($path, 'tag/'),
            str_starts_with($path, 'author/'), preg_match('#^\d{4}(/\d{2}){0,2}(/.*)?$#', $path) === 1 => '/media/news',
            default => null,
        };
    }

    /**
     * قسم رابط رئيسية بمعامل ووردبريس (/?p=12)، أو null.
     *
     * @param  array<string, mixed>  $query
     */
    public static function forQuery(array $query): ?string
    {
        foreach (self::QUERY_KEYS as $key => $target) {
            if (array_key_exists($key, $query)) {
                return $target;
            }
        }

        return null;
    }
}
