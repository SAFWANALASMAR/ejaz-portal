<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * توقيع مرسوم في اللوحة (حقل التوقيع في النماذج الإلكترونية): يصل صورة PNG بترميز base64، فيُفحص أنها
 * PNG حقيقية بحجم وأبعاد معقولة، ثم يُعاد ترميزها بمكتبة GD (فلا يبقى فيها غير الصورة)، وتُحفظ باسم
 * عشوائي في القرص الخاص.
 */
final class SignatureImage
{
    public const MAX_BYTES = 300_000;

    private const PREFIX = 'data:image/png;base64,';

    /**
     * @return string مسار الصورة في القرص
     *
     * @throws ValidationException
     */
    public static function store(string $dataUrl, string $disk, string $directory, string $field): string
    {
        $bytes = str_starts_with($dataUrl, self::PREFIX) ? base64_decode(substr($dataUrl, strlen(self::PREFIX)), true) : false;
        $size = $bytes === false ? false : @getimagesizefromstring($bytes);

        if ($bytes === false || strlen($bytes) > self::MAX_BYTES || ! str_starts_with($bytes, "\x89PNG\r\n\x1a\n")
            || $size === false || $size[2] !== IMAGETYPE_PNG || $size[0] > 2000 || $size[1] > 1000) {
            throw ValidationException::withMessages([$field => __('documents.validation.invalid_signature')]);
        }

        $image = @imagecreatefromstring($bytes);

        if ($image === false) {
            throw ValidationException::withMessages([$field => __('documents.validation.invalid_signature')]);
        }

        imagesavealpha($image, true);
        ob_start();
        imagepng($image, null, 6);
        $clean = (string) ob_get_clean();

        $path = $directory.'/'.Str::random(40).'.png';
        Storage::disk($disk)->put($path, $clean);

        return $path;
    }

    /**
     * التوقيع المحفوظ صورةً بترميز base64، لتعرضه لوحة الرسم عند التعديل والطباعة.
     */
    public static function dataUrl(string $disk, string $path): ?string
    {
        $contents = Storage::disk($disk)->get($path);

        return $contents === null ? null : self::PREFIX.base64_encode($contents);
    }
}
