<?php

namespace App\Support;

/**
 * صورة المشاركة (og:image وtwitter:image) برابط مطلق: صورة الصفحة إن كانت PNG أو JPEG أو WebP، وإلا صورة
 * الموقع الافتراضية. والافتراضية الآن شعار الجمعية، حتى تصل صورة مشاركة 1200×630 منها (open-questions.md).
 */
class ShareImage
{
    public const DEFAULT = 'images/brand/logo-mark.png';

    /**
     * أبعاد الصورة الافتراضية بالبكسل (og:image:width وog:image:height)، وتتغير معها إن استُبدلت.
     */
    public const DEFAULT_WIDTH = 495;

    public const DEFAULT_HEIGHT = 505;

    /**
     * الرابط المطلق (من url()) هو الصورة الافتراضية.
     */
    public static function isDefault(string $url): bool
    {
        return $url === asset(self::DEFAULT);
    }

    public static function url(?string $image = null): string
    {
        $path = $image !== null ? (string) parse_url($image, PHP_URL_PATH) : '';

        return $image !== null && preg_match('/\.(png|jpe?g|webp)$/i', $path) === 1
            ? url($image)
            : asset(self::DEFAULT);
    }
}
