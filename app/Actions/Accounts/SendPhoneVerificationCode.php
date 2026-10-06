<?php

namespace App\Actions\Accounts;

use App\Enums\RoleName;
use App\Jobs\SendSms;
use App\Models\User;
use App\Notifications\SmsCapWarning;
use App\Support\NetworkPrefix;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

/**
 * يرسل رمز تحقق من ستة أرقام إلى جوال المستخدم.
 * الرمز يُخزَّن مُجزَّأً (hash) في الذاكرة المؤقتة لعشر دقائق، ولا يُخزَّن نصاً.
 *
 * الحدود (config/security.php — وdocs/performance.md):
 * - لكل مستخدم: رمز كل دقيقة، وخمسة رموز في الساعة.
 * - لكل شبكة: عدد الرموز المعلّقة (أُرسلت ولم يُتحقق منها). الرمز الجديد يحل محل
 *   السابق في العدّ، والتحقق الناجح يحرر مكانه، فلا تتضرر الشبكات المشتركة.
 *   IPv4: الشبكة /24. وIPv6: المشترك /64 بحد صارم، والموقع /48 بحد أوسع.
 * - للبوابة كلها: سقف في الساعة، مع تنبيه المشرف العام عند 70% و90% قبل التوقف.
 */
class SendPhoneVerificationCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    private const PENDING_WINDOW = 3600;

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

        $previous = Cache::get(self::cacheKey($user));
        $previousBuckets = is_array($previous) ? (array) ($previous['buckets'] ?? []) : [];
        $buckets = self::buckets(request()->ip());

        foreach ($buckets as $key => $max) {
            // الرمز السابق لهذا المستخدم من الشبكة نفسها يُستبدل ولا يُضاف
            $pending = RateLimiter::attempts($key) - (in_array($key, $previousBuckets, true) ? 1 : 0);

            if ($pending >= $max) {
                Log::warning('Too many pending SMS codes from one network.', ['network' => $key]);

                throw ValidationException::withMessages(['code' => __('account.phone.network_busy')]);
            }
        }

        $cap = (int) config('security.sms_per_hour');

        if (RateLimiter::tooManyAttempts('sms:global', $cap)) {
            Log::critical('SMS hourly cap reached; verification codes are paused.');

            throw ValidationException::withMessages(['code' => __('account.phone.unavailable')]);
        }

        RateLimiter::hit('otp-minute:'.$user->id, 60);
        RateLimiter::hit('otp-hour:'.$user->id, 3600);
        $sent = RateLimiter::hit('sms:global', 3600);

        self::releaseBuckets($previousBuckets);

        foreach (array_keys($buckets) as $key) {
            RateLimiter::increment($key, self::PENDING_WINDOW);
        }

        $this->warnBeforeCap($sent, $cap);

        $code = (string) random_int(100000, 999999);

        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::cacheKey($user), [
            'hash' => self::hash($code, $phone),
            'phone' => $phone,
            'buckets' => array_keys($buckets),
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        SendSms::dispatch($phone, __('account.phone.sms', [
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
        ]));
    }

    /**
     * سلال العدّ لعنوان الزائر وحدّ كل منها. العنوان الخاص أو المجهول بلا سلة
     * (يعني غالباً أن الوكلاء الموثوقين غير مضبوطين)، ويبقى السقف العام حامياً.
     *
     * @return array<string, int>
     */
    public static function buckets(?string $ip): array
    {
        $ip = NetworkPrefix::normalize($ip);

        if ($ip === null || ! NetworkPrefix::isPublic($ip)) {
            return [];
        }

        if (NetworkPrefix::isIpv6($ip)) {
            return [
                'sms-pending:'.NetworkPrefix::prefix($ip, 64) => (int) config('security.sms_pending_per_ipv6_subscriber'),
                'sms-pending:'.NetworkPrefix::prefix($ip, 48) => (int) config('security.sms_pending_per_ipv6_site'),
            ];
        }

        return ['sms-pending:'.NetworkPrefix::prefix($ip, 24) => (int) config('security.sms_unverified_per_network')];
    }

    /**
     * تحرير أماكن رمز معلّق (عند التحقق منه أو استبداله).
     *
     * @param  array<int, mixed>  $buckets
     */
    public static function releaseBuckets(array $buckets): void
    {
        foreach ($buckets as $key) {
            if (is_string($key) && RateLimiter::attempts($key) > 0) {
                RateLimiter::decrement($key, self::PENDING_WINDOW);
            }
        }
    }

    /**
     * تنبيه واحد لكل عتبة في الساعة، ليرفع المشرف السقف قبل أن يتوقف التسجيل.
     */
    private function warnBeforeCap(int $sent, int $cap): void
    {
        foreach ((array) config('security.sms_alert_thresholds') as $percent) {
            if ($sent < (int) ceil($cap * $percent / 100)) {
                continue;
            }

            if (! Cache::add('sms-cap-alert:'.$percent, true, 3600)) {
                continue;
            }

            Log::critical("SMS codes reached {$percent}% of the hourly cap.", ['sent' => $sent, 'cap' => $cap]);

            Notification::send(
                User::role(RoleName::SuperAdmin->value)->where('is_active', true)->get(),
                new SmsCapWarning((int) $percent, $sent, $cap),
            );
        }
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
