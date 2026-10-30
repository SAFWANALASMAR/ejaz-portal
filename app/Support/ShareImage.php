<?php

namespace App\Support;

/**
 * صورة المشاركة (og:image وtwitter:image) برابط مطلق: صورة الصفحة إن كانت PNG أو JPEG أو WebP، وإلا صورة
 * الموقع الافتراضية. والافتراضية الآن شعار الجمعية، حتى تصل صورة مشاركة 1200×630 منها (open-questions.md).
 */
class ShareImage
{
    public const DEFAULT = 'images/brand/logo-mark.png';

    public static function url(?string $image = null): string
    {
        $path = is_string($image) ? (string) parse_url($image, PHP_URL_PATH) : '';

        return $image !== null && preg_match('/\.(png|jpe?g|webp)$/i', $path) === 1
            ? url($image)
            : asset(self::DEFAULT);
    }
}
