<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * النص المنسق من محرر اللوحة يُعرض في الموقع العام بعد تنقية صارمة (symfony/html-sanitizer،
 * المثبت مع Filament):
 * - عناصر النص الأساسية فقط، بلا سمة style، لأن سياسة أمان المحتوى تسمح بالأنماط المضمّنة.
 * - والروابط https وmailto والداخلية فقط، وتُفتح بلا وصول إلى الصفحة الأصل.
 * وما سواها يُحذف: لا سكربت، ولا معالجات أحداث، ولا javascript:، ولو وصلت إلى القاعدة.
 */
final class RichText
{
    private static ?HtmlSanitizer $sanitizer = null;

    public static function toHtml(?string $html): HtmlString
    {
        return new HtmlString(self::sanitizer()->sanitize((string) $html));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        $config = new HtmlSanitizerConfig;

        foreach (['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'hr'] as $element) {
            $config = $config->allowElement($element);
        }

        return self::$sanitizer ??= new HtmlSanitizer(
            $config
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'mailto'])
                ->allowRelativeLinks()
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(100_000),
        );
    }
}
