<?php

namespace App\Support;

use Illuminate\Support\HtmlString;
use Symfony\Component\HtmlSanitizer\HtmlSanitizer;
use Symfony\Component\HtmlSanitizer\HtmlSanitizerConfig;

/**
 * النص المنسق من محرر اللوحة يُعرض في الموقع العام بعد تنقية صارمة (symfony/html-sanitizer،
 * المثبت مع Filament — docs/decisions.md):
 * - عناصر النص الأساسية فقط، بلا سمة style، لأن سياسة أمان المحتوى تسمح بالأنماط المضمّنة.
 * - والروابط بقاعدة البوابة نفسها (SafeLinkSanitizer)، وتُفتح بلا وصول إلى الصفحة الأصل.
 * وما سواها يُحذف: لا سكربت، ولا معالجات أحداث، ولا javascript:، ولو وصلت إلى القاعدة.
 */
final class RichText
{
    /**
     * أقصى طول للنص المنسق، في المحرر وفي التنقية.
     */
    public const MAX_LENGTH = 100_000;

    private const ELEMENTS = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'ul', 'ol', 'li', 'blockquote', 'hr'];

    /**
     * عناصر تنسيق تأتي مع النص الملصوق (من مستند أو صفحة): يُحذف العنصر ويبقى نصه.
     * وما سواها يُحذف بمحتواه، ومنه script وstyle.
     */
    private const UNWRAPPED = ['div', 'span', 'section', 'article', 'font', 'center', 'small', 'sub', 'sup', 'mark', 'pre', 'code',
        'table', 'thead', 'tbody', 'tfoot', 'tr', 'td', 'th', 'caption', 'figure', 'figcaption', 'h1', 'h5', 'h6'];

    private static ?HtmlSanitizer $sanitizer = null;

    public static function toHtml(?string $html): HtmlString
    {
        return new HtmlString(self::sanitizer()->sanitize((string) $html));
    }

    private static function sanitizer(): HtmlSanitizer
    {
        if (self::$sanitizer !== null) {
            return self::$sanitizer;
        }

        $config = new HtmlSanitizerConfig;

        foreach (self::ELEMENTS as $element) {
            $config = $config->allowElement($element);
        }

        foreach (self::UNWRAPPED as $element) {
            $config = $config->blockElement($element);
        }

        return self::$sanitizer = new HtmlSanitizer(
            $config
                ->allowElement('a', ['href'])
                ->allowLinkSchemes(['https', 'mailto'])
                ->allowRelativeLinks()
                ->withAttributeSanitizer(new SafeLinkSanitizer)
                ->forceAttribute('a', 'rel', 'noopener noreferrer')
                ->withMaxInputLength(self::MAX_LENGTH),
        );
    }
}
