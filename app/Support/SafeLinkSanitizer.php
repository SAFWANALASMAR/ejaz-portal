<?php

namespace App\Support;

use App\Rules\SafeUrl;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;
use Symfony\Component\HtmlSanitizer\Visitor\AttributeSanitizer\AttributeSanitizerInterface;

/**
 * روابط النص المنسق بقاعدة البوابة نفسها (App\Rules\SafeUrl): https بمضيف، أو مسار داخلي لا
 * يبدأ بـ //، أو بريد mailto صالح. وما سواها يُحذف رابطه ويبقى نصه.
 */
final class SafeLinkSanitizer implements AttributeSanitizerInterface
{
    /**
     * @return list<string>
     */
    public function getSupportedElements(): array
    {
        return ['a'];
    }

    /**
     * @return list<string>
     */
    public function getSupportedAttributes(): array
    {
        return ['href'];
    }

    public function sanitizeAttribute(string $element, string $attribute, string $value, HtmlSanitizerConfig $config): ?string
    {
        if (str_starts_with(strtolower($value), 'mailto:')) {
            return filter_var(substr($value, 7), FILTER_VALIDATE_EMAIL) !== false ? $value : null;
        }

        return SafeUrl::isSafe($value) ? $value : null;
    }
}
