<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * يعيد ترميز الصورة المرفوعة بمكتبة GD، فتُزال بياناتها الوصفية (EXIF ومنها الموقع
 * الجغرافي) وأي محتوى مخفي خلف بياناتها. يحفظها بالصيغة نفسها وفي المسار نفسه.
 */
final class ImageSanitizer
{
    public static function strip(string $disk, string $path): void
    {
        $storage = Storage::disk($disk);
        $contents = $storage->get($path);

        $image = $contents === null ? false : @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException("Not a readable image: {$path}");
        }

        $type = (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) $contents);

        ob_start();
        match ($type) {
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, 85),
            default => imagejpeg($image, null, 85),
        };
        $clean = (string) ob_get_clean();

        $storage->put($path, $clean);
    }
}
