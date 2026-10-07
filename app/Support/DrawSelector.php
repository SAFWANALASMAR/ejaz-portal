<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * اختيار الفائزين من بذرة: خلط Fisher-Yates جزئي، كل خطوة فيه برقم من HMAC-SHA256 للبذرة بلا انحياز
 * (رفض ما يتجاوز أكبر مضاعف للمدى). فالبذرة نفسها مع القائمة نفسها تعطي الفائزين أنفسهم دائماً، ويُعاد
 * السحب أمام اللجنة للتحقق. والبذرة من المولّد الآمن (RunDraw)، فلا يتوقعها أحد قبل التنفيذ.
 */
final class DrawSelector
{
    /**
     * @param  list<string>  $entrants  مرتبة ترتيباً ثابتاً
     * @return list<string>
     */
    public static function winners(string $seed, array $entrants, int $count): array
    {
        if (! preg_match('/^[0-9a-f]{64}$/', $seed) || $count < 1 || $count > count($entrants)) {
            throw new InvalidArgumentException('Invalid draw input.');
        }

        $key = (string) hex2bin($seed);
        $pool = $entrants;
        $size = count($pool);

        for ($i = 0; $i < $count; $i++) {
            $j = $i + self::uniform($key, $i, $size - $i);
            [$pool[$i], $pool[$j]] = [$pool[$j], $pool[$i]];
        }

        return array_slice($pool, 0, $count);
    }

    /**
     * بصمة قائمة المشمولين: SHA-256 لعناصرها مفصولة بسطر.
     *
     * @param  list<string>  $entrants
     */
    public static function fingerprint(array $entrants): string
    {
        return hash('sha256', implode("\n", $entrants));
    }

    /**
     * رقم منتظم التوزيع في [0, المدى) للخطوة المعطاة.
     */
    private static function uniform(string $key, int $step, int $range): int
    {
        $space = 2 ** 48;
        $limit = intdiv($space, $range) * $range;

        for ($attempt = 0; ; $attempt++) {
            $bytes = substr(hash_hmac('sha256', $step.':'.$attempt, $key, true), 0, 6);
            $value = (int) (unpack('J', "\0\0".$bytes)[1] ?? 0);

            if ($value < $limit) {
                return $value % $range;
            }
        }
    }
}
