<?php

namespace App\Support;

/**
 * صورة المشاركة (og:image وtwitter:image) برابط مطلق: صورة الصفحة إن كانت PNG أو JPEG أو WebP، وإلا صورة
 * الموقع الافتراضية. والافتراضية 1200×630 من عناصر الهوية وحدها (الرمز والمخطوطة واسم الجمعية على تدرجها — فحص 11 نوفمبر
 * 2026، يرسمها scripts/share-image/render.mjs)، حتى تصل صورة المشاركة المعتمدة من الجمعية فتحل محلها (open-questions.md).
 */
class ShareImage
{
    public const DEFAULT = 'images/brand/share-default.jpg';

    /**
     * أبعاد الصورة الافتراضية بالبكسل (og:image:width وog:image:height)، وتتغير معها إن استُبدلت.
     */
    public const DEFAULT_WIDTH = 1200;

    public const DEFAULT_HEIGHT = 630;

    /**
     * شعار الجمعية المربع للبيانات المنظمة (Organization.logo)، لا صورة المشاركة العريضة.
     */
    public const LOGO = 'images/brand/logo-mark.png';

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
