<?php

namespace App\Support;

/**
 * ألوان مربعات الأيقونات المتدرجة (x-icon-tile — التصور النهائي، صفوان 30 أكتوبر 19:18): ستة بالترتيب، والبطاقة تأخذ لونها من
 * موضعها (index)، فيبقى ثابتاً بين العرض والآخر. وتدرج كل لون في resources/css/concept.css، وتباين الأيقونة البيضاء على طرفيه
 * مختبر في tests/Unit/IconTileContrastTest.php.
 */
final class IconTones
{
    public const ORDER = ['blue', 'violet', 'green', 'navy', 'sky', 'lilac'];

    public static function at(int $index): string
    {
        return self::ORDER[(($index % count(self::ORDER)) + count(self::ORDER)) % count(self::ORDER)];
    }
}
