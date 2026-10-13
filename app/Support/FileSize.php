<?php

namespace App\Support;

use Closure;

/**
 * حجم الملف للعرض: بالكيلوبايت، أو بالميجابايت برقم عشري واحد. ولا شيء لحجم غير معروف.
 */
final class FileSize
{
    /**
     * @param  (Closure(string, array<string, int|string>): string)|null  $translate  للاختبار؛ الافتراضي __()
     */
    public static function display(?int $bytes, ?Closure $translate = null): ?string
    {
        if (! $bytes) {
            return null;
        }

        $translate ??= fn (string $key, array $replace): string => (string) __($key, $replace);

        return $bytes >= 1048576
            ? $translate('site.file.size_mb', ['size' => number_format($bytes / 1048576, 1)])
            : $translate('site.file.size_kb', ['size' => max(1, (int) round($bytes / 1024))]);
    }
}
