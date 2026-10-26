<?php

namespace App\Auth;

use App\Models\User;
use App\Support\LoginIdentifier;
use App\Support\NetworkPrefix;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * قفل محاولات الدخول الخاطئة (الدخول الناجح لا يُحسب):
 * - خمس محاولات في الدقيقة لكل حساب وعنوان.
 * - عشر محاولات في ربع ساعة لكل معرّف من أي عنوان، ومثلها للحساب نفسه بأي معرّف من معرّفاته (قفل مؤقت للحساب).
 * - لكل شبكة في ربع ساعة: المشترك (عنوان IPv4، أو /64 في IPv6)، والموقع /48 في IPv6،
 *   فلا يخمّن مصدر واحد كلمات مرور حسابات كثيرة. والدخول الناجح لا يمسحه.
 * ومعها حارسان للمعالج في الدقيقة:
 * - سقف عام للمحاولات الخاطئة في البوابة كلها. والناجحة لا تُحسب منه، فلا يملؤه صاحب حساب صحيح
 *   بتكرار الدخول فيغلق الدخول عن الجميع (مراجعة التكامل، 14 أكتوبر 2026).
 * - حد لكل المحاولات من كل مشترك، ناجحة أو خاطئة، فلا يُنهك مصدر واحد المعالج بدخول صحيح متكرر.
 *
 * يعمل القفل في EnsureLoginIsNotThrottled قبل فحص كلمة المرور، فالمحجوب لا يكلّف تجزئة.
 * المفتاح هو المعرّف الموحَّد، فلا يتجاوز القفلَ تغييرُ صيغة الرقم (docs/decisions.md).
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    private const ALL_KEY = 'login-all';

    /**
     * كل الحدود: حدود الحساب ثم حدود الشبكة.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    protected function limits(Request $request): array
    {
        return $this->accountLimits($request) + $this->networkLimits($request);
    }

    /**
     * حدود الحساب، يمسحها الدخول الناجح.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    private function accountLimits(Request $request): array
    {
        $identifier = LoginIdentifier::normalize($request->input(Fortify::username()));
        $limits = [
            'login:'.$identifier.'|'.$request->ip() => ['max' => 5, 'decay' => 60],
            'login-account:'.$identifier => ['max' => 10, 'decay' => 900],
        ];

        // وللحساب نفسه أياً كان معرّفه (اسم المستخدم أو البريد أو الجوال)، فلا تتضاعف المحاولات بتبديل المعرّف
        // (مراجعة المشرف المبكرة، ٧)
        $id = $this->accountId($request, $identifier);

        if ($id !== null) {
            $limits['login-user:'.$id] = ['max' => 10, 'decay' => 900];
        }

        return $limits;
    }

    /**
     * معرّف الحساب صاحب المعرّف، مرة واحدة في الطلب (تُحسب الحدود أكثر من مرة في المحاولة الواحدة).
     */
    private function accountId(Request $request, string $identifier): ?int
    {
        $key = 'login_rate_limiter.account:'.$identifier;

        if (! $request->attributes->has($key)) {
            $request->attributes->set($key, User::findByLoginIdentifier($identifier)?->getKey());
        }

        $id = $request->attributes->get($key);

        return is_int($id) ? $id : null;
    }

    /**
     * حدود الشبكة للمحاولات الخاطئة، لا يمسحها الدخول الناجح حتى لا يصفّرها المهاجم بحسابه.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    private function networkLimits(Request $request): array
    {
        $ip = NetworkPrefix::normalize($request->ip());

        $limits = [
            'login-network:'.NetworkPrefix::subscriber($ip) => ['max' => (int) config('security.login_failures_per_subscriber'), 'decay' => 900],
        ];

        if ($ip !== null && NetworkPrefix::isIpv6($ip)) {
            $limits['login-network:'.NetworkPrefix::prefix($ip, 48)] = ['max' => (int) config('security.login_failures_per_ipv6_site'), 'decay' => 900];
        }

        return $limits;
    }

    /**
     * @param  Request  $request
     */
    public function attempts($request): int
    {
        return max(array_map(fn (string $key): int => (int) $this->limiter->attempts($key), array_keys($this->limits($request))));
    }

    /**
     * السقف العام للمحاولات الخاطئة في البوابة (0 بلا سقف): يُفحص قبل كلمة المرور، ويُحسب مع الخاطئة وحدها.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    private function portalLimits(): array
    {
        $max = (int) config('security.login_failures_per_minute');

        return $max > 0 ? [self::ALL_KEY => ['max' => $max, 'decay' => 60]] : [];
    }

    /**
     * حد كل المحاولات من المشترك (0 بلا حد): يُفحص ويُحسب قبل كلمة المرور، ناجحة كانت أو خاطئة.
     *
     * @return array<string, array{max: int, decay: int}>
     */
    private function attemptLimits(Request $request): array
    {
        $max = (int) config('security.login_attempts_per_subscriber_per_minute');

        return $max > 0
            ? ['login-attempts:'.NetworkPrefix::subscriber(NetworkPrefix::normalize($request->ip())) => ['max' => $max, 'decay' => 60]]
            : [];
    }

    /**
     * يُستدعى مرة لكل محاولة قبل فحص كلمة المرور، فيُحسب فيه حد كل المحاولات من المشترك.
     *
     * @param  Request  $request
     */
    public function tooManyAttempts($request): bool
    {
        foreach ($this->limits($request) + $this->portalLimits() + $this->attemptLimits($request) as $key => $limit) {
            if ($this->limiter->tooManyAttempts($key, $limit['max'])) {
                return true;
            }
        }

        foreach ($this->attemptLimits($request) as $key => $limit) {
            $this->limiter->hit($key, $limit['decay']);
        }

        return false;
    }

    /**
     * المحاولة الخاطئة وحدها: حدود الحساب والشبكة، والسقف العام.
     *
     * @param  Request  $request
     */
    public function increment($request): void
    {
        foreach ($this->limits($request) + $this->portalLimits() as $key => $limit) {
            $this->limiter->hit($key, $limit['decay']);
        }
    }

    /**
     * @param  Request  $request
     */
    public function availableIn($request): int
    {
        // مدة الانتظار من الحد الذي بُلغ فعلاً، لا من أطول الحدود
        $waits = [];

        foreach ($this->limits($request) + $this->portalLimits() + $this->attemptLimits($request) as $key => $limit) {
            if ($this->limiter->tooManyAttempts($key, $limit['max'])) {
                $waits[] = $this->limiter->availableIn($key);
            }
        }

        return $waits === [] ? 0 : max($waits);
    }

    /**
     * الدخول الناجح يمسح حدود الحساب وحدها.
     *
     * @param  Request  $request
     */
    public function clear($request): void
    {
        foreach (array_keys($this->accountLimits($request)) as $key) {
            $this->limiter->clear($key);
        }
    }
}
