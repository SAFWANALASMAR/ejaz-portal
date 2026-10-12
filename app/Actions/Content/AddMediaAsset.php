<?php

namespace App\Actions\Content;

use App\Models\MediaAsset;
use App\Models\User;
use App\Rules\PlainText;
use App\Support\SvgSanitizer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use League\Flysystem\FilesystemException;
use RuntimeException;
use Throwable;

/**
 * يضيف صورة أو أيقونة إلى مكتبة الوسائط من ملف على الخادم (أمر الاستيراد): يُفحص نوعه من محتواه،
 * ويُحفظ أصله في القرص الخاص باسم عشوائي، وتُصنع للصورة نسخة ويب WebP مصغّرة في القرص العام.
 * والأيقونة SVG تُنظَّف قبل حفظها، ولا نسخة عامة لها.
 */
class AddMediaAsset
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * أقصى عرض أو ارتفاع لنسخة الويب.
     */
    public const WEB_MAX_SIDE = 1920;

    public const WEB_QUALITY = 82;

    /**
     * أقصى ضلع للصورة الأصلية قبل فك ترميزها، فلا يستنفد ملف صغير بأبعاد ضخمة الذاكرة.
     */
    public const MAX_SIDE = 6000;

    /**
     * أقصى عدد للبكسلات (قرابة 4900×4900)، فلا تحجز صورة مربعة بأقصى ضلعها ذاكرة الأمر كلها.
     */
    public const MAX_PIXELS = 24_000_000;

    private const RASTER_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

    /**
     * @param  array<string, mixed>  $data  slug، title، alt، is_decorative، from_template، legacy_url
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, string $file, array $data): MediaAsset
    {
        if (! $actor->can('create', MediaAsset::class)) {
            throw new AuthorizationException;
        }

        $values = Validator::make($data, [
            'slug' => ['required', 'string', 'max:80', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:media_assets,slug'],
            'title' => ['required', 'string', 'max:200', new PlainText],
            'alt' => ['nullable', 'string', 'max:300', new PlainText],
            'is_decorative' => ['sometimes', 'boolean'],
            'from_template' => ['sometimes', 'boolean'],
            'legacy_url' => ['nullable', 'string', 'max:1000'],
        ])->validate();

        $contents = is_file($file) && filesize($file) <= self::MAX_BYTES ? file_get_contents($file) : false;
        $type = $contents === false ? null : (new \finfo(FILEINFO_MIME_TYPE))->buffer($contents);
        $isSvg = in_array($type, ['image/svg+xml', 'text/xml'], true)
            && strtolower(pathinfo($file, PATHINFO_EXTENSION)) === 'svg';

        if ($contents === false || (! $isSvg && ! isset(self::RASTER_TYPES[(string) $type]))) {
            throw ValidationException::withMessages(['file' => __('import.errors.media_type', ['file' => basename($file)])]);
        }

        $decorative = (bool) ($values['is_decorative'] ?? false);
        $attributes = [
            'slug' => $values['slug'],
            'title' => $values['title'],
            'original_name' => mb_substr(basename($file), 0, 255),
            'alt' => $decorative ? null : ($values['alt'] ?? null),
            'is_decorative' => $decorative,
            'from_template' => (bool) ($values['from_template'] ?? false),
            'legacy_url' => $values['legacy_url'] ?? null,
        ];

        $stored = [];

        try {
            if ($isSvg) {
                $clean = SvgSanitizer::clean($contents);
                $attributes += ['mime_type' => 'image/svg+xml', 'size' => strlen($clean)];
                $attributes['original_path'] = $stored[] = self::put(MediaAsset::disk(), MediaAsset::ORIGINALS_DIRECTORY, 'svg', $clean);
            } else {
                [$web, $width, $height] = self::webVersion($contents);
                $attributes += ['mime_type' => (string) $type, 'size' => strlen($contents), 'width' => $width, 'height' => $height, 'web_size' => strlen($web)];
                $attributes['original_path'] = $stored[] = self::put(MediaAsset::disk(), MediaAsset::ORIGINALS_DIRECTORY, self::RASTER_TYPES[(string) $type], $contents);
                $attributes['web_path'] = $stored[] = self::put('public', MediaAsset::WEB_DIRECTORY, 'webp', $web);
            }

            return MediaAsset::query()->create($attributes);
        } catch (Throwable $exception) {
            Storage::disk(MediaAsset::disk())->delete($attributes['original_path'] ?? '');
            Storage::disk('public')->delete($attributes['web_path'] ?? '');

            // الملف الذي لا يُقرأ صورةً أو أيقونةً سليمة وحده «ليس صورة»، وغيره (القاعدة مثلاً) يُرمى كما هو
            // وأعطال التخزين (Flysystem) ترث RuntimeException أيضاً، فتُستثنى ليظهر سببها
            throw $exception instanceof RuntimeException && ! $exception instanceof FilesystemException
                ? ValidationException::withMessages(['file' => __('import.errors.media_type', ['file' => basename($file)])])
                : $exception;
        }
    }

    /**
     * ينسخ نسخة الويب إلى مجلد سجل على القرص العام (صورة مقطع في صفحة، أو صورة الرئيسية)، فيملك
     * السجل نسخته، ولا يحذف تعديلُه أو حذفُه ملفَّ المكتبة.
     */
    public static function placeIn(MediaAsset $asset, string $directory): ?string
    {
        if ($asset->web_path === null || ! Storage::disk('public')->exists($asset->web_path)) {
            return null;
        }

        $path = $directory.'/'.Str::random(40).'.webp';
        Storage::disk('public')->copy($asset->web_path, $path);

        return $path;
    }

    /**
     * نسخة WebP بلا بيانات وصفية، مصغّرة إن زاد ضلعها على الحد، بشفافيتها.
     *
     * @return array{0: string, 1: int, 2: int}
     */
    private static function webVersion(string $contents): array
    {
        $size = @getimagesizefromstring($contents);

        if ($size === false || $size[0] < 1 || $size[1] < 1 || max($size[0], $size[1]) > self::MAX_SIDE || $size[0] * $size[1] > self::MAX_PIXELS) {
            throw new RuntimeException('Unreadable or oversized image');
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            throw new RuntimeException('Unreadable image');
        }

        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, self::WEB_MAX_SIDE / max($width, $height));

        if ($scale < 1) {
            $image = imagescale($image, max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)), IMG_BICUBIC) ?: $image;
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        ob_start();
        imagewebp($image, null, self::WEB_QUALITY);

        return [(string) ob_get_clean(), $width, $height];
    }

    private static function put(string $disk, string $directory, string $extension, string $contents): string
    {
        $path = $directory.'/'.Str::random(40).'.'.$extension;
        Storage::disk($disk)->put($path, $contents);

        return $path;
    }
}
