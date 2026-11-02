<?php

namespace App\Support;

use App\Jobs\GenerateHeroVariants;
use Illuminate\Support\Facades\Storage;

/**
 * مصادر صورة شريحة البطل في الرئيسية: نسختان WebP بعرض 800 و1600 مع srcset متى وُلّدتا (GenerateHeroVariants)، وإلا الأصل
 * وطلب توليدهما في الطابور. واسم النسخة من بصمة مسار الأصل، فتتجدد بتغيّره. والصورة الأولى وحدها بأولوية التحميل،
 * والبقية كسولة (components/hero-slider).
 */
final class HeroImage
{
    /** @var list<int> */
    public const WIDTHS = [800, 1600];

    public const QUALITY = 80;

    public const DIRECTORY = 'hero-variants';

    public static function variantPath(string $path, int $width): string
    {
        return self::DIRECTORY.'/'.sha1($path).'-'.$width.'.webp';
    }

    /**
     * علامة الصورة التي رفضها التوليد (تالفة أو أكبر من الحد)، ملف فارغ بجانب النسخ، فلا تُرسل مهمتها عند كل بناء للرئيسية
     * (مراجعة المشرف على #111، المنخفضة 4). واسمها من بصمة المسار، فالصورة المستبدلة بمسار جديد تُجرَّب من جديد.
     */
    public static function failedPath(string $path): string
    {
        return self::DIRECTORY.'/'.sha1($path).'.failed';
    }

    public static function markFailed(string $path): void
    {
        Storage::disk('public')->put(self::failedPath($path), '');
    }

    /**
     * صورة نقطية مرفوعة في القرص العام (لا رابط خارجي ولا SVG ولا مسار بنقاط صاعدة).
     */
    public static function accepts(string $path): bool
    {
        return preg_match('#^[A-Za-z0-9_/-]+\.(jpe?g|png|webp)$#i', $path) === 1 && ! str_contains($path, '..');
    }

    /**
     * @return array{src: string, srcset: ?string}
     */
    public static function sources(string $path): array
    {
        $disk = Storage::disk('public');

        if (! self::accepts($path)) {
            return ['src' => $disk->url($path), 'srcset' => null];
        }

        if ($disk->exists(self::failedPath($path))) {
            return ['src' => $disk->url($path), 'srcset' => null];
        }

        foreach (self::WIDTHS as $width) {
            if (! $disk->exists(self::variantPath($path, $width))) {
                GenerateHeroVariants::dispatch($path);

                return ['src' => $disk->url($path), 'srcset' => null];
            }
        }

        $srcset = implode(', ', array_map(fn (int $width): string => $disk->url(self::variantPath($path, $width)).' '.$width.'w', self::WIDTHS));

        return ['src' => $disk->url(self::variantPath($path, max(self::WIDTHS))), 'srcset' => $srcset];
    }
}
