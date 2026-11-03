<?php

namespace App\Rules;

use App\Enums\SocialPlatform;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * رابط المنشور الأصلي: https خارجي آمن (SafeUrl)، ومضيفه نطاق المنصة المختارة أو نطاق فرعي منه (SocialPlatform::hosts)،
 * بحروف النطاق اللاتينية وحدها كقاعدة رابط المتجر (StoreUrl). وبلا منصة صحيحة يُرفض الرابط.
 */
class SocialPostUrl implements ValidationRule
{
    public const MAX_LENGTH = 500;

    public function __construct(private ?SocialPlatform $platform) {}

    /**
     * الرابط بلا مسافات حوله، وبلا الشرطة المائلة الأخيرة في المسار (لا في معامل الاستعلام).
     */
    public static function normalize(string $url): string
    {
        $url = trim($url);

        return str_contains($url, '?') || str_contains($url, '#') ? $url : rtrim($url, '/');
    }

    public static function matches(mixed $url, ?SocialPlatform $platform): bool
    {
        if ($platform === null || ! is_string($url) || strlen($url) > self::MAX_LENGTH || ! str_starts_with($url, 'https://') || ! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $host = strtolower((string) parse_url($url, PHP_URL_HOST));

        // لا ترميز نسبة ولا حروف خارج النطاق، فلا يُلتف على المقارنة (مثل evil.com%2F.x.com)
        if (preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $host) !== 1) {
            return false;
        }

        foreach ($platform->hosts() as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.'.$allowed)) {
                return true;
            }
        }

        return false;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::matches(is_string($value) ? self::normalize($value) : $value, $this->platform)) {
            $fail(__('social_posts.validation.url', ['hosts' => implode('، ', $this->platform?->hosts() ?? [])]));
        }
    }
}
