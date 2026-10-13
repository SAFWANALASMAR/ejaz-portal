<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط آمن للعرض: مسار داخلي يبدأ بـ / ، أو رابط كامل يبدأ بـ https://
 *
 * يُرفض:
 * - javascript: وdata: وhttp: وما شابهها.
 * - الروابط التي تبدأ بـ // أو /\ (تعاملها بعض المتصفحات رابطاً خارجياً).
 * - بيانات المستخدم قبل النطاق (https://ejaz.sa@evil.example يذهب إلى evil.example).
 * - الشرطة العكسية، وأحرف التحكم والتنسيق (ومنها محارف اتجاه النص المخفية)،
 *   والمسافات بكل أنواعها، صريحةً أو مرمّزة.
 * - النص الذي ليس UTF-8 صالحاً بعد فك الترميز.
 */
class SafeUrl implements ValidationRule
{
    public function __construct(private bool $allowInternal = true) {}

    public static function isSafe(?string $url, bool $allowInternal = true): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        $decoded = rawurldecode($url);

        foreach ([$url, $decoded] as $candidate) {
            if (! mb_check_encoding($candidate, 'UTF-8')) {
                return false;
            }

            if (preg_match('/[\p{C}\p{Z}\\\\]/u', $candidate) === 1) {
                return false;
            }
        }

        if ($allowInternal && str_starts_with($url, '/')) {
            return ! str_starts_with($decoded, '//');
        }

        $parts = parse_url($url);

        return is_array($parts)
            && strtolower($parts['scheme'] ?? '') === 'https'
            && ($parts['host'] ?? '') !== ''
            && ! isset($parts['user'])
            && ! isset($parts['pass']);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value, $this->allowInternal)) {
            $fail($this->allowInternal ? 'validation.safe_url' : 'validation.safe_external_url')->translate();
        }
    }
}
