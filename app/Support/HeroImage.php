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
