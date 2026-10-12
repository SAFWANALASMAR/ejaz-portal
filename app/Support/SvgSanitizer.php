<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMXPath;
use RuntimeException;

/**
 * ينظّف أيقونة SVG قبل حفظها: عناصر رسم معروفة وحدها (لا سكربت ولا foreignObject ولا تحميل
 * خارجي)، ولا معالجات أحداث (on*)، ولا رابط إلا إلى عنصر في الملف نفسه (#)، ولا قيمة فيها
 * javascript: أو data: أو url() إلى خارج الملف. ويُرفض الملف بتعريف DOCTYPE (الكيانات الخارجية).
 */
final class SvgSanitizer
{
    private const ELEMENTS = [
        'svg', 'g', 'path', 'rect', 'circle', 'ellipse', 'line', 'polyline', 'polygon', 'defs', 'symbol', 'use',
        'lineargradient', 'radialgradient', 'stop', 'clippath', 'mask', 'pattern', 'title', 'desc', 'text', 'tspan', 'style',
    ];

    public const MAX_BYTES = 1024 * 1024;

    /**
     * @throws RuntimeException ملف ليس SVG سليماً
     */
    public static function clean(string $svg): string
    {
        if (strlen($svg) > self::MAX_BYTES || stripos($svg, '<!DOCTYPE') !== false || stripos($svg, '<!ENTITY') !== false) {
            throw new RuntimeException('Rejected SVG');
        }

        $document = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $loaded = $document->loadXML($svg, LIBXML_NONET);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        // والفحص بعد التحليل أيضاً، فلا يمر DOCTYPE بترميز آخر (UTF-16) لم يره فحص النص
        if (! $loaded || $document->doctype !== null || $document->documentElement === null || strtolower($document->documentElement->localName) !== 'svg') {
            throw new RuntimeException('Not an SVG');
        }

        // التعليمات البرمجية والتعليقات لا مكان لها في أيقونة
        foreach ((new DOMXPath($document))->query('//processing-instruction()|//comment()') ?: [] as $node) {
            $node->parentNode?->removeChild($node);
        }

        self::cleanElement($document->documentElement);

        return (string) $document->saveXML($document->documentElement);
    }

    private static function cleanElement(DOMElement $element): void
    {
        foreach (iterator_to_array($element->childNodes) as $child) {
            if (! $child instanceof DOMElement) {
                continue;
            }

            $name = strtolower($child->localName);

            if (! in_array($name, self::ELEMENTS, true) || ($name === 'style' && ! self::isSafeValue($child->textContent))) {
                $element->removeChild($child);

                continue;
            }

            self::cleanElement($child);
        }

        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->localName);
            $value = $attribute->value;

            $unsafe = str_starts_with($name, 'on')
                || ($name === 'href' && ! str_starts_with(trim($value), '#'))
                || ! self::isSafeValue($value);

            if ($unsafe) {
                $element->removeAttributeNode($attribute);
            }
        }
    }

    /**
     * قيمة لا تنفّذ شيئاً ولا تحمّل من خارج الملف.
     */
    private static function isSafeValue(string $value): bool
    {
        $compact = (string) preg_replace('/\s+/', '', strtolower($value));

        // ولا تهريب CSS (u\72l أو @\69mport) يخفي ما يُفحص هنا
        return ! str_contains($compact, '\\') && ! str_contains($compact, 'javascript:') && ! str_contains($compact, 'vbscript:') && ! str_contains($compact, 'data:')
            && ! str_contains($compact, '@import') && ! str_contains($compact, 'expression(')
            && preg_match('/url\((?![\'"]?#)/', $compact) !== 1;
    }
}
