<?php

namespace App\Actions\Accounts;

use App\Models\User;
use App\Notifications\EmailVerificationCode;
use App\Support\LoginIdentifier;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * يرسل رمز توثيق من ستة أرقام إلى بريد المستخدم (قرار اجتماع 6 نوفمبر 2026 — decisions/2026-11-06-email-otp.md): رمز
 * البريد مؤقتاً بدل رمز الجوال حتى يُفعَّل واتساب. والرمز مُجزَّأ (hash) في الذاكرة المؤقتة عشر دقائق، مربوط بالبريد
 * الذي أُرسل إليه، ولا يُخزَّن نصاً.
 *
 * الحدود: لكل حساب رمز في الدقيقة وخمسة في الساعة، ولكل بريد مستهدف خمسة في الساعة وعشرة في اليوم أياً كان الحساب،
 * فلا يُقصف بريد برسائل بنقله بين حسابات، وسقف للبوابة كلها في الساعة (reserveAddress)، ورابط التوثيق تحت الحدود نفسها.
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

        if (($refused = self::reserveAddress($email)) !== null) {
            throw ValidationException::withMessages(['code' => __($refused)]);
        }

        RateLimiter::hit('email-otp-minute:'.$user->id, 60);
        RateLimiter::hit('email-otp-hour:'.$user->id, 3600);

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

    /**
     * حدود البريد المستهدف وسقف البوابة لكل رسالة توثيق إلى بريد، رمزاً كانت أو رابطاً (User::sendEmailVerificationNotification
     * — مراجعة الأمن على #207، م١ وم٢): يحسبها ويرد null إن سُمح، وإلا مفتاح رسالة الرفض بلا حساب.
     */
    public static function reserveAddress(string $email): ?string
    {
        // مفتاح البريد ببصمته، فلا يُخزَّن البريد نصاً في مفاتيح الحدود
        $address = LoginIdentifier::hash($email);

        foreach (self::ADDRESS_LIMITS as [$prefix, $max]) {
            if (RateLimiter::tooManyAttempts($prefix.$address, $max)) {
                return 'account.email_code.address_busy';
            }
        }

        // سقف البوابة في الساعة دون حد مزود البريد، فلا يُحجب صندوق الجمعية فيتوقف الرمز عن الجميع
        $cap = (int) config('security.email_codes_per_hour');

        if (RateLimiter::tooManyAttempts('email-otp:global', $cap)) {
            if (Cache::add('email-otp-cap-alert', true, 3600)) {
                Log::critical('Email verification hourly cap reached; codes are paused.', ['cap' => $cap]);
            }

            return 'account.phone.unavailable';
        }

        foreach (self::ADDRESS_LIMITS as [$prefix, , $decay]) {
            RateLimiter::hit($prefix.$address, $decay);
        }

        RateLimiter::hit('email-otp:global', 3600);

        return null;
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
