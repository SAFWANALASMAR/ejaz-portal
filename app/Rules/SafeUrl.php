<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط آمن للعرض: مسار داخلي يبدأ بـ / ، أو رابط كامل يبدأ بـ https:// (أو http://).
 * يمنع javascript: وdata: والروابط التي تبدأ بـ // أو /\ (تعاملها بعض المتصفحات رابطاً
 * خارجياً)، ويمنع الشرطة العكسية والمسافات وأحرف التحكم، مرمّزةً أو صريحة.
 */
class SafeUrl implements ValidationRule
{
    public function __construct(private bool $allowInternal = true) {}

    public static function isSafe(?string $url, bool $allowInternal = true): bool
    {
        if ($url === null || $url === '') {
            return false;
        }

        // يُفحص الرابط كما هو، وبعد فك الترميز (مثل %5C و%2F)
        $decoded = rawurldecode($url);

        foreach ([$url, $decoded] as $candidate) {
            if (preg_match('/[\x00-\x20\x7F\\\\]/u', $candidate) === 1) {
                return false;
            }
        }

        if ($allowInternal && str_starts_with($url, '/')) {
            return ! str_starts_with($decoded, '//');
        }

        return preg_match('#^https?://[^/?\#]+#i', $url) === 1;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isSafe($value, $this->allowInternal)) {
            $fail('validation.safe_url')->translate();
        }
    }
}
