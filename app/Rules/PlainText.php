<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * نص من زائر: UTF-8 صالح بلا محارف تحكم. فلا يصل إلى القاعدة نص ترفضه، فتسقط الصفحة بخطأ
 * 500، بل يعود الزائر إلى نموذجه كأي مدخل خاطئ. والنص متعدد الأسطر (الرسالة) يقبل الأسطر
 * الجديدة والجدولة وحدها.
 */
class PlainText implements ValidationRule
{
    public function __construct(private bool $multiline = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $control = $this->multiline ? '/(?![\n\r\t])\p{Cc}/u' : '/\p{Cc}/u';

        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match($control, $value) === 1) {
            $fail('validation.string')->translate();
        }
    }
}
