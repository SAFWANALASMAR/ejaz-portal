<?php

namespace App\Rules;

use App\Support\Digits;
use App\Support\SaudiPhone;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * كلمة مرور لا تحوي بيانات صاحبها (قرار صفوان، 20 أكتوبر 2026): جزءاً من الاسم طوله 3 أحرف فأكثر، ولا ما قبل @ في
 * البريد، ولا رقم الجوال الوطني (9 أرقام)، ولا اسم المستخدم ولا جزءاً منه. بلا حساسية لحالة الأحرف، وبعد توحيد الأرقام العربية.
 */
class PasswordWithoutPersonalData implements ValidationRule
{
    public function __construct(private ?string $name = null, private ?string $email = null, private ?string $phone = null, private ?string $username = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            return;
        }

        $password = mb_strtolower(Digits::latin($value));

        foreach ($this->fragments() as $fragment) {
            if (str_contains($password, $fragment)) {
                $fail('account.password_rules.personal')->translate();

                return;
            }
        }
    }

    /**
     * @return list<string>
     */
    private function fragments(): array
    {
        $fragments = preg_split('/[\s\-_.]+/u', mb_strtolower(trim((string) $this->name))) ?: [];

        // اسم المستخدم كاملاً وأجزاؤه (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)
        $username = mb_strtolower(trim((string) $this->username));
        $fragments = [...$fragments, $username, ...(preg_split('/[._]+/', $username) ?: [])];

        $local = mb_strtolower((string) strstr((string) $this->email, '@', true));
        $fragments[] = $local;

        $phone = SaudiPhone::normalize($this->phone);
        if ($phone !== null) {
            $fragments[] = substr($phone, -9);
        }

        return array_values(array_unique(array_filter($fragments, fn (string $fragment) => mb_strlen($fragment) >= 3)));
    }
}
