<?php

namespace App\Actions\Accounts;

use App\Jobs\SendSms;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * يرسل رمز تحقق من ستة أرقام إلى جوال المستخدم.
 * الرمز يُخزَّن مُجزَّأً (hash) في الذاكرة المؤقتة لعشر دقائق، ولا يُخزَّن نصاً.
 * الحد: رمز كل دقيقة، وخمسة رموز في الساعة لكل مستخدم.
 */
class SendPhoneVerificationCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    public function handle(User $user): void
    {
        $phone = $user->phone;

        if ($phone === null) {
            throw ValidationException::withMessages(['code' => __('account.phone.no_phone')]);
        }

        foreach ([['otp-minute:', 1, 60], ['otp-hour:', 5, 3600]] as [$prefix, $max, $decay]) {
            $key = $prefix.$user->id;

            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'code' => __('account.phone.wait', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        RateLimiter::hit('otp-minute:'.$user->id, 60);
        RateLimiter::hit('otp-hour:'.$user->id, 3600);

        $code = (string) random_int(100000, 999999);

        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::cacheKey($user), [
            'hash' => self::hash($code, $phone),
            'phone' => $phone,
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        SendSms::dispatch($phone, __('account.phone.sms', [
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
        ]));
    }

    public static function cacheKey(User $user): string
    {
        return 'phone-otp:'.$user->id;
    }

    public static function hash(string $code, string $phone): string
    {
        return hash_hmac('sha256', $code.'|'.$phone, (string) config('app.key'));
    }
}
