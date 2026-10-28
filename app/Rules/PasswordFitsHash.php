<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * bcrypt لا يقرأ من كلمة المرور إلا أول 72 بايتاً، وما بعدها يُهمل بصمت: كلمتا مرور تتفقان فيها تدخلان معاً، والرقم
 * والرمز في آخر كلمة مرور طويلة لا يُحسبان. والحرف العربي بايتان، فالحد 72 حرفاً لاتينياً أو 36 عربياً (ASVS 2.1.2،
 * مطابقة المعايير — 20 أكتوبر 2026). فلا تُقبل كلمة مرور أطول مما يُجزَّأ كاملاً.
 */
class PasswordFitsHash implements ValidationRule
{
    public const MAX_BYTES = 72;

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && strlen($value) > self::MAX_BYTES) {
            $fail('account.password_rules.too_long')->translate();
        }
    }
}
