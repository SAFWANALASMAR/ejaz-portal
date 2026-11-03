<?php

namespace App\Jobs;

use App\Support\ContentCache;
use App\Support\HeroImage;
use App\Support\ImageSanitizer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

/**
 * نسخ صورة شريحة البطل المصغّرة (HeroImage::WIDTHS بصيغة WebP) من الأصل المرفوع في القرص العام (مراجعة المشرف على #111:
 * صورة ألبوم حتى 4MB و3000 بكسل كانت تُحمَّل كاملة في الشاشة الأولى). في الطابور، فلا يتأخر طلب الزائر بفك صورة كبيرة،
 * ثم تُمسح ذاكرة المحتوى فتظهر النسخ في الرئيسية. وبلا مكتبة: GD كبقية معالجة الصور (AddMediaAsset).
 */
class GenerateHeroVariants implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    /** @var list<int> المحاولة الثانية بعد مهلة، لا فوراً على قرص أو ذاكرة متعثرة */
    public array $backoff = [30, 120];

    /** صورة حتى ImageSanitizer::MAX_PIXELS تُفك وتُصغَّر مرتين */
    public int $timeout = 120;

    public function __construct(public readonly string $path) {}

    public function uniqueId(): string
    {
        return $this->path;
    }

    public function handle(): void
    {
        $disk = Storage::disk('public');

        if (! HeroImage::accepts($this->path) || ! $disk->exists($this->path)) {
            return;
        }

        $contents = (string) $disk->get($this->path);
        $size = @getimagesizefromstring($contents);

        if ($size === false || $size[0] < 1 || $size[0] * $size[1] > ImageSanitizer::MAX_PIXELS) {
            HeroImage::markFailed($this->path);

            return;
        }

        $image = @imagecreatefromstring($contents);

        if ($image === false) {
            HeroImage::markFailed($this->path);

            return;
        }

        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        // من الأعرض إلى الأضيق، وكل نسخة تُصغَّر من التي قبلها لا من الأصل، فلا تُفك الصورة الكبيرة إلا مرة
        $widths = HeroImage::WIDTHS;
        rsort($widths);

        foreach ($widths as $width) {
            if (imagesx($image) > $width) {
                $image = imagescale($image, $width, max(1, (int) round(imagesy($image) * $width / imagesx($image))), IMG_BICUBIC) ?: $image;
            }

            ob_start();
            imagewebp($image, null, HeroImage::QUALITY);
            $disk->put(HeroImage::variantPath($this->path, $width), (string) ob_get_clean());
        }

        unset($image);

        // الرئيسية وحدها تعرض الشرائح، فلا تفقد بقية الصفحات تخزينها (مراجعة المشرف على #111، المنخفضة 3)
        ContentCache::forget('home');
    }
}
