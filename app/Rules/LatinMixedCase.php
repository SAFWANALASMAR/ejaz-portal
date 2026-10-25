<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * حرف لاتيني كبير وآخر صغير متى كان في كلمة المرور حرف لاتيني (قرار صفوان، 20 أكتوبر 2026). والعربية بلا حالة
 * أحرف، فلا يلزمها ذلك، بخلاف mixedCase في لارافيل الذي يرفض كلمة المرور العربية الحروف كلها.
 */
class LatinMixedCase implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/[A-Za-z]/', $value) !== 1) {
            return;
        }

        if (preg_match('/[A-Z]/', $value) !== 1 || preg_match('/[a-z]/', $value) !== 1) {
            $fail('validation.password.mixed')->translate();
        }
    }
}
