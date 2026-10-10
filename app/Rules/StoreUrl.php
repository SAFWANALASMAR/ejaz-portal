<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط متجر الجمعية: https خارجي آمن، ومضيفه أحد النطاقات المسموحة في config/store.php أو نطاق فرعي منها.
 */
class StoreUrl implements ValidationRule
{
    public static function isAllowed(mixed $url): bool
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        foreach ((array) config('store.allowed_hosts') as $allowed) {
            $allowed = strtolower((string) $allowed);

            if ($allowed !== '' && ($host === $allowed || str_ends_with($host, '.'.$allowed))) {
                return true;
            }
        }

        return false;
    }

    /**
     * رابط المتجر المضبوط إن صح، وإلا الرابط المعتمد.
     */
    public static function current(mixed $configured): string
    {
        return self::isAllowed($configured) ? (string) $configured : (string) config('store.url');
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! self::isAllowed($value)) {
            $fail(__('settings.store.url_invalid', ['hosts' => implode('، ', (array) config('store.allowed_hosts'))]));
        }
    }
}
