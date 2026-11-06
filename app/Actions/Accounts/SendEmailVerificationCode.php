<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Support\LoginIdentifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * يرسل رمز توثيق من ستة أرقام إلى بريد المستخدم (قرار اجتماع 6 نوفمبر 2026 — decisions/2026-11-06-email-otp.md): رمز
 * البريد مؤقتاً بدل رمز الجوال حتى يُفعَّل واتساب. والرمز مُجزَّأ (hash) في الذاكرة المؤقتة عشر دقائق، مربوط بالبريد
 * الذي أُرسل إليه، ولا يُخزَّن نصاً.
 *
 * الحدود: لكل حساب رمز في الدقيقة وخمسة في الساعة، ولكل بريد مستهدف خمسة في الساعة وعشرة في اليوم أياً كان الحساب،
 * فلا يُقصف بريد برسائل بنقله بين حسابات. وسقف إنشاء الحسابات يحد ما يُرسل من التسجيل (CreateNewUser).
 */
class SendEmailVerificationCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * حدود البريد المستهدف: [بادئة المفتاح، الحد، المدة بالثواني].
     */
    private const ADDRESS_LIMITS = [
        ['email-otp-address-hour:', 5, 3600],
        ['email-otp-address-day:', 10, 86400],
    ];

    public function handle(User $user): void
    {
        $email = $user->email;

        if ($email === null) {
            throw ValidationException::withMessages(['code' => __('account.email_code.no_email')]);
        }

        foreach ([['email-otp-minute:', 1], ['email-otp-hour:', 5]] as [$prefix, $max]) {
            if (RateLimiter::tooManyAttempts($prefix.$user->id, $max)) {
                throw ValidationException::withMessages([
                    'code' => __('account.phone.wait', ['seconds' => RateLimiter::availableIn($prefix.$user->id)]),
                ]);
            }
        }

        // مفتاح البريد ببصمته، فلا يُخزَّن البريد نصاً في مفاتيح الحدود
        $address = LoginIdentifier::hash($email);

        foreach (self::ADDRESS_LIMITS as [$prefix, $max]) {
            if (RateLimiter::tooManyAttempts($prefix.$address, $max)) {
                throw ValidationException::withMessages(['code' => __('account.email_code.address_busy')]);
            }
        }

        RateLimiter::hit('email-otp-minute:'.$user->id, 60);
        RateLimiter::hit('email-otp-hour:'.$user->id, 3600);

        foreach (self::ADDRESS_LIMITS as [$prefix, , $decay]) {
            RateLimiter::hit($prefix.$address, $decay);
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::cacheKey($user), [
            'hash' => self::hash($code, $email),
            'email' => $email,
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        $user->notify(new EmailVerificationCode($code, self::TTL_MINUTES));
    }

    public static function cacheKey(User $user): string
    {
        return 'email-otp:'.$user->id;
    }

    public static function hash(string $code, string $email): string
    {
        return hash_hmac('sha256', $code.'|'.$email, (string) config('app.key'));
    }
}
