<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * نص من زائر: UTF-8 صالح بلا محارف تحكم ولا محارف قلب الاتجاه (التي تقلب ظاهر النص في البريد
 * واللوحة). فلا يصل إلى القاعدة نص ترفضه، فتسقط الصفحة بخطأ 500، بل يعود الزائر إلى نموذجه كأي
 * مدخل خاطئ. والنص متعدد الأسطر (الرسالة) يقبل الأسطر الجديدة والجدولة وحدها.
 */
class PlainText implements ValidationRule
{
    public function __construct(private bool $multiline = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $control = $this->multiline ? '/(?![\n\r\t])\p{Cc}/u' : '/\p{Cc}/u';

        if (! is_string($value) || ! mb_check_encoding($value, 'UTF-8') || preg_match($control, $value) === 1
            || preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}]/u', $value) === 1) {
            $fail('validation.string')->translate();
        }
    }
}
