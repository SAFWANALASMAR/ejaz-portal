<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * نص بحث أو تصفية من زائر: UTF-8 صالح بلا محارف تحكم. فلا يصل إلى القاعدة نص ترفضه، فتسقط
 * الصفحة بخطأ 500، بل يعود الزائر إلى القائمة كأي مدخل خاطئ.
 */
class PlainText implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match('/\p{Cc}/u', $value) === 1) {
            $fail('validation.string')->translate();
        }
    }
}
