<?php

namespace App\Support;

/**
 * درجات مربع الأيقونة الأربع (x-icon-tile — أمر التصور النهائي، النسخة 63، البند R5): فاتحة جداً، ثم فاتحة، ثم متوسطة، ثم
 * غامقة، من الأزرق وحده. وتتعاقب في الصفحة من CSS بموضع البطاقة (resources/css/concept.css)، فلا يلزم القالب أن يمرر شيئاً.
 * وهذه القائمة للدرجة الصريحة (shade) ولما يلوَّن ببطاقته خارج المربع (بطاقات «خدماتنا»). وتباين الرسم على طرفي كل درجة
 * مختبر في tests/Unit/IconTileContrastTest.php.
 */
final class IconTones
{
    public const ORDER = ['pale', 'light', 'medium', 'dark'];

    /**
     * الدرجات التي رسمها أبيض، والباقيتان برسم الأزرق الداكن.
     */
    public const WHITE_GLYPH = ['medium', 'dark'];

    public static function at(int $index): string
    {
        return self::ORDER[(($index % count(self::ORDER)) + count(self::ORDER)) % count(self::ORDER)];
    }
}
