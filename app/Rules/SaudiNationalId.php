<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * صيغة رقم الهوية الوطنية أو الإقامة: عشرة أرقام، يبدأ بـ 1 (مواطن) أو 2 (مقيم)، وآخرها رقم تحقق
 * بخوارزمية Luhn. فحص صيغة لا تحقق من الهوية نفسها.
 */
class SaudiNationalId implements ValidationRule
{
    public static function isValid(string $value): bool
    {
        if (preg_match('/^[12]\d{9}$/', $value) !== 1) {
            return false;
        }

        $sum = 0;

        foreach (str_split($value) as $position => $digit) {
            $number = (int) $digit;

            if ($position % 2 === 0) {
                $number *= 2;
                $number = $number > 9 ? $number - 9 : $number;
            }

            $sum += $number;
        }

        return $sum % 10 === 0;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! self::isValid($value)) {
            $fail('competitions.exam.national_id_invalid')->translate();
        }
    }
}
