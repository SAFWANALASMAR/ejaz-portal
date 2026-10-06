<?php

namespace App\Support;

/**
 * توحيد معرّف الدخول قبل استعماله في البحث عن الحساب، وفي مفتاح قفل المحاولات،
 * وفي بصمة السجل الأمني. فالصيغ 0501234567 و+966501234567 و٠٥٠١٢٣٤٥٦٧ معرّف واحد،
 * ولا تتضاعف المحاولات المسموحة بتغيير الصيغة.
 */
final class LoginIdentifier
{
    public static function normalize(?string $identifier): string
    {
        $value = trim((string) $identifier);

        if ($value === '') {
            return '';
        }

        if (str_contains($value, '@')) {
            return mb_strtolower($value);
        }

        return SaudiPhone::normalize($value) ?? mb_strtolower(Digits::latin($value));
    }

    public static function isEmail(string $normalized): bool
    {
        return str_contains($normalized, '@');
    }

    /**
     * بصمة المعرّف للسجل الأمني، حتى لا يُخزَّن المعرّف نصاً. بمفتاح التطبيق، لأن أرقام
     * الجوال قليلة الاحتمالات فتُعكس بصمتها بلا مفتاح في ثوانٍ. وتدوير المفتاح يقطع الربط
     * بالبصمات القديمة، ومحاولات الدخول على حساب موجود مربوطة به في السجل مباشرة.
     */
    public static function hash(?string $identifier): ?string
    {
        $normalized = self::normalize($identifier);

        return $normalized === '' ? null : hash_hmac('sha256', $normalized, (string) config('app.key'));
    }
}
