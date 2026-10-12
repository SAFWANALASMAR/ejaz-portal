<?php

namespace App\Support;

use GdImage;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * يعيد ترميز الصورة المرفوعة بمكتبة GD، فتُزال بياناتها الوصفية (EXIF ومنها الموقع
 * الجغرافي) وأي محتوى مخفي خلف بياناتها. يحفظها بالصيغة نفسها وفي المسار نفسه،
 * مع الإبقاء على الشفافية، وتعديل اتجاه صورة الجوال قبل حذف وسم الاتجاه.
 */
final class ImageSanitizer
{
    /**
     * أقصى عدد للبكسلات قبل فك الصورة: صورة صغيرة الحجم بأبعاد ضخمة تستنزف ذاكرة GD.
     */
    public const MAX_PIXELS = 40_000_000;

    /**
     * @throws RuntimeException إن لم يكن الملف صورة تقرؤها GD
     */
    public static function strip(string $disk, string $path): void
    {
        $storage = Storage::disk($disk);
        $contents = $storage->get($path);

        $size = $contents === null ? false : @getimagesizefromstring($contents);

        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException("Not a readable image, or too many pixels: {$path}");
        }

        $image = @imagecreatefromstring((string) $contents);

        if ($image === false) {
            throw new RuntimeException("Not a readable image: {$path}");
        }

        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $contents);

        if ($type === 'image/jpeg') {
            $image = self::upright($image, (string) $contents);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        match ($type) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 85),
        };
        $clean = (string) ob_get_clean();

        $storage->put($path, $clean);
    }

    /**
     * وسم الاتجاه (EXIF Orientation) يُحذف مع بقية البيانات الوصفية، فتُدار الصورة
     * قبله، وإلا ظهرت صورة الجوال مائلة أو مقلوبة.
     */
    private static function upright(GdImage $image, string $contents): GdImage
    {
        $stream = fopen('php://memory', 'r+b');

        if ($stream === false) {
            return $image;
        }

        fwrite($stream, $contents);
        rewind($stream);
        $exif = @exif_read_data($stream);
        fclose($stream);

        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        if (in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($image, IMG_FLIP_HORIZONTAL);
        }

        // GD يدير عكس عقارب الساعة
        $angle = match ($orientation) {
            3, 4 => 180,
            5, 8 => 90,
            6, 7 => 270,
            default => 0,
        };

        return $angle === 0 ? $image : (imagerotate($image, $angle, 0) ?: $image);
    }
}
