<?php

namespace App\Auth;

use App\Support\LoginIdentifier;
use Illuminate\Http\Request;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * قفل محاولات الدخول الخاطئة فقط (الدخول الناجح لا يُحسب، ويمسح العدّاد):
 * - خمس محاولات في الدقيقة لكل حساب وعنوان.
 * - عشر محاولات في ربع ساعة لكل حساب من أي عنوان (قفل مؤقت للحساب).
 *
 * المفتاح هو المعرّف الموحَّد، فلا يتجاوز القفلَ تغييرُ صيغة الرقم (docs/decisions.md).
 */
class LoginRateLimiter extends FortifyLoginRateLimiter
{
    /**
     * @return array<string, array{max: int, decay: int}>
     */
    protected function limits(Request $request): array
    {
        $identifier = LoginIdentifier::normalize($request->input(Fortify::username()));

        return [
            'login:'.$identifier.'|'.$request->ip() => ['max' => 5, 'decay' => 60],
            'login-account:'.$identifier => ['max' => 10, 'decay' => 900],
        ];
    }

    /**
     * @param  Request  $request
     */
    public function attempts($request): int
    {
        return max(array_map(fn (string $key): int => (int) $this->limiter->attempts($key), array_keys($this->limits($request))));
    }

    /**
     * @param  Request  $request
     */
    public function tooManyAttempts($request): bool
    {
        foreach ($this->limits($request) as $key => $limit) {
            if ($this->limiter->tooManyAttempts($key, $limit['max'])) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  Request  $request
     */
    public function increment($request): void
    {
        foreach ($this->limits($request) as $key => $limit) {
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

        foreach ($this->limits($request) as $key => $limit) {
            if ($this->limiter->tooManyAttempts($key, $limit['max'])) {
                $waits[] = $this->limiter->availableIn($key);
            }
        }

        return $waits === [] ? 0 : max($waits);
    }

    /**
     * @param  Request  $request
     */
    public function clear($request): void
    {
        foreach (array_keys($this->limits($request)) as $key) {
            $this->limiter->clear($key);
        }
    }
}
