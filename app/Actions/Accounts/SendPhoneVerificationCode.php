<?php

namespace App\Actions\Accounts;

use App\Enums\RoleName;
use App\Jobs\SendSms;
use App\Models\User;
use App\Notifications\SmsCapWarning;
use App\Support\LoginIdentifier;
use App\Support\NetworkPrefix;
use App\Support\SaudiPhone;
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
 * - لكل رقم مستهدف: خمسة رموز في الساعة وعشرة في اليوم، أياً كان الحساب الذي يطلبها،
 *   فلا يُقصف رقم برسائل بنقله بين حسابات.
 * - لكل شبكة حدان: الرموز المعلّقة (أُرسلت ولم يُتحقق منها)، والرمز الجديد يحل محل
 *   السابق فيه، والتحقق الناجح يحرر مكانه، فلا تتضرر الشبكات المشتركة. وكل رسالة
 *   مرسلة، بلا استبدال ولا تحرير، فلا تضاعف إعادةُ الإرسال حدَّ الشبكة.
 *   IPv4: الشبكة /24. وIPv6: المشترك /64 بحد صارم، والموقع /48.
 * - للبوابة كلها: سقف في الساعة، مع تنبيه المشرف العام والمسؤول التقني عند 70% و90% قبل التوقف.
 */
class SendPhoneVerificationCode
{
    public const TTL_MINUTES = 10;

    public const MAX_ATTEMPTS = 5;

    /**
     * غرض الرمز: توثيق الجوال، أو استعادة كلمة المرور به (ResetPasswordByPhone، قرار صفوان 20 أكتوبر 2026). لكل غرض
     * مفتاح رمزه ونص رسالته. وحدود الحساب والرقم لكل غرض وحده (مراجعة المشرف على #64): فمن يعرف الرقم ويستنزف رموز الاستعادة
     * لا يحرم صاحبه رمز التوثيق. وحدود الشبكة وسقف البوابة مشتركة بينهما، فلا يُضاعف الغرض الثاني ما يُرسل من شبكة.
     */
    public const VERIFY = 'verify';

    public const RESET = 'reset';

    private const PENDING_WINDOW = 3600;

    /**
     * حدود الرقم المستهدف: [بادئة المفتاح، الحد، المدة بالثواني].
     */
    private const NUMBER_LIMITS = [
        ['otp-number-hour:', 5, 3600],
        ['otp-number-day:', 10, 86400],
    ];

    public function handle(User $user, string $purpose = self::VERIFY): void
    {
        // التوثيق إلى الرقم المعلّق إن وُجد، والاستعادة إلى الرقم الموثق وحده
        $phone = $purpose === self::VERIFY ? $user->phoneToVerify() : $user->phone;

        if ($phone === null) {
            throw ValidationException::withMessages(['code' => __('account.phone.no_phone')]);
        }

        // جوال سعودي وحده (كالتسجيل و«بياناتي» وشاشة المستخدمين: SaudiMobile)، فلا يُرسل رمز إلى رقم دولي
        // مكلف وصل إلى الحساب من طريق آخر (استيراد أو تعديل في القاعدة)
        if (SaudiPhone::normalize($phone) !== $phone) {
            throw ValidationException::withMessages(['code' => __('account.phone.not_saudi')]);
        }

        // الاستعادة بمفاتيح مستقلة، والتوثيق بمفاتيحه القائمة كما هي
        $scope = $purpose === self::RESET ? 'reset:' : '';

        foreach ([['otp-minute:', 1], ['otp-hour:', 5]] as [$prefix, $max]) {
            $key = $prefix.$scope.$user->id;

            if (RateLimiter::tooManyAttempts($key, $max)) {
                throw ValidationException::withMessages([
                    'code' => __('account.phone.wait', ['seconds' => RateLimiter::availableIn($key)]),
                ]);
            }
        }

        // مفتاح الرقم ببصمته، فلا يُخزَّن الرقم نصاً في مفاتيح الحدود
        $number = $scope.LoginIdentifier::hash($phone);

        foreach (self::NUMBER_LIMITS as [$prefix, $max]) {
            if (RateLimiter::tooManyAttempts($prefix.$number, $max)) {
                throw ValidationException::withMessages(['code' => __('account.phone.number_busy')]);
            }
        }

        $previous = Cache::get(self::cacheKey($user, $purpose));
        $previousBuckets = is_array($previous) ? (array) ($previous['buckets'] ?? []) : [];
        $networks = self::networks(request()->ip());

        if ($networks === []) {
            $this->warnWithoutNetworkLimit();
        }

        foreach ($networks as $network => $limit) {
            // الرمز السابق لهذا المستخدم من الشبكة نفسها يُستبدل في عدّ المعلّق ولا يُضاف
            $pendingKey = 'sms-pending:'.$network;
            $pending = RateLimiter::attempts($pendingKey) - (in_array($pendingKey, $previousBuckets, true) ? 1 : 0);

            if ($pending >= $limit['pending'] || RateLimiter::tooManyAttempts('sms-sent:'.$network, $limit['sent'])) {
                Log::warning('Too many SMS codes from one network.', ['network' => $network]);

                throw ValidationException::withMessages(['code' => __('account.phone.network_busy')]);
            }
        }

        $cap = (int) config('security.sms_per_hour');

        if (RateLimiter::tooManyAttempts('sms:global', $cap)) {
            Log::critical('SMS hourly cap reached; verification codes are paused.');

            throw ValidationException::withMessages(['code' => __('account.phone.unavailable')]);
        }

        RateLimiter::hit('otp-minute:'.$scope.$user->id, 60);
        RateLimiter::hit('otp-hour:'.$scope.$user->id, 3600);

        foreach (self::NUMBER_LIMITS as [$prefix, , $decay]) {
            RateLimiter::hit($prefix.$number, $decay);
        }

        $sent = RateLimiter::hit('sms:global', 3600);

        self::releaseBuckets($previousBuckets);

        foreach (array_keys($networks) as $network) {
            RateLimiter::increment('sms-pending:'.$network, self::PENDING_WINDOW);
            RateLimiter::hit('sms-sent:'.$network, 3600);
        }

        $this->warnBeforeCap($sent, $cap);

        $code = (string) random_int(100000, 999999);

        $expiresAt = now()->addMinutes(self::TTL_MINUTES);

        Cache::put(self::cacheKey($user, $purpose), [
            'hash' => self::hash($code, $phone),
            'phone' => $phone,
            'buckets' => array_map(fn (string $network) => 'sms-pending:'.$network, array_keys($networks)),
            'attempts' => 0,
            'expires_at' => $expiresAt->getTimestamp(),
        ], $expiresAt);

        SendSms::dispatch($phone, __($purpose === self::RESET ? 'account.password_reset.sms' : 'account.phone.sms', [
            'code' => $code,
            'minutes' => self::TTL_MINUTES,
        ]));
    }

    /**
     * شبكات عنوان الزائر وحدّا كل منها. العنوان الخاص أو المجهول بلا شبكة
     * (يعني غالباً أن الوكلاء الموثوقين غير مضبوطين)، ويبقى السقف العام حامياً.
     *
     * @return array<string, array{pending: int, sent: int}>
     */
    public static function networks(?string $ip): array
    {
        $ip = NetworkPrefix::normalize($ip);

        if ($ip === null || ! NetworkPrefix::isPublic($ip)) {
            return [];
        }

        if (NetworkPrefix::isIpv6($ip)) {
            return [
                NetworkPrefix::prefix($ip, 64) => self::limits('ipv6_subscriber'),
                NetworkPrefix::prefix($ip, 48) => self::limits('ipv6_site'),
            ];
        }

        return [NetworkPrefix::prefix($ip, 24) => self::limits('ipv4_network')];
    }

    /**
     * @return array{pending: int, sent: int}
     */
    private static function limits(string $scope): array
    {
        return [
            'pending' => (int) config("security.sms_pending_per_{$scope}"),
            'sent' => (int) config("security.sms_sent_per_{$scope}"),
        ];
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
     * العنوان الخاص أو المجهول يعني غالباً أن TRUSTED_PROXIES غير مضبوط، فيتوقف حد
     * الشبكة عن الجميع بصمت. تحذير واحد في الساعة يكشف ذلك في السجل.
     */
    private function warnWithoutNetworkLimit(): void
    {
        if (Cache::add('sms-network-unknown-alert', true, 3600)) {
            Log::warning('SMS network limits are off: the client address is private or unknown. Check TRUSTED_PROXIES.', [
                'ip' => request()->ip(),
            ]);
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
                User::role(RoleName::values(RoleName::overseers()))->where('is_active', true)->get(),
                new SmsCapWarning((int) $percent, $sent, $cap),
            );
        }
    }

    public static function cacheKey(User $user, string $purpose = self::VERIFY): string
    {
        return ($purpose === self::RESET ? 'password-reset-otp:' : 'phone-otp:').$user->id;
    }

    public static function hash(string $code, string $phone): string
    {
        return hash_hmac('sha256', $code.'|'.$phone, (string) config('app.key'));
    }
}
