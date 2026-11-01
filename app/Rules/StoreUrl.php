<?php

namespace App\Rules;

use App\Models\Setting;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط متجر الجمعية: https خارجي آمن، ومضيفه أحد النطاقات المسموحة في config/store.php مطابقةً تامة،
 * والنطاقات الفرعية بنمط صريح وحده (*.ejaz.org.sa). والمضيف حروف النطاق اللاتينية وحدها (punycode).
 */
class StoreUrl implements ValidationRule
{
    public static function isAllowed(mixed $url): bool
    {
        if (! is_string($url) || ! str_starts_with($url, 'https://') || ! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // لا ترميز نسبة ولا حروف خارج النطاق، فلا يُلتف على المقارنة (مثل evil.com%2F.ejaz.org.sa)
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
            return false;
        }

        foreach ((array) config('store.allowed_hosts') as $allowed) {
            $allowed = strtolower((string) $allowed);

            if (str_starts_with($allowed, '*.') ? str_ends_with($host, substr($allowed, 1)) : $host === $allowed) {
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

    /**
     * رابط المتجر لروابط التذييل وبطاقة «خدماتنا» (1 نوفمبر 2026): المضبوط في إعدادات الموقع، وإلا المعتمد، وكلاهما بالنطاقات
     * المسموحة. وnull إن لم يصح أي منهما، فلا يظهر الرابط.
     */
    public static function link(): ?string
    {
        $url = self::current(Setting::group('store')['url'] ?? null);

        return self::isAllowed($url) ? $url : null;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (filled($value) && ! self::isAllowed($value)) {
            $fail(__('settings.store.url_invalid', ['hosts' => implode('، ', (array) config('store.allowed_hosts'))]));
        }
    }
}
