<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;

/**
 * يتحقق من رمز البريد (SendEmailVerificationCode). خمس محاولات خاطئة تُبطل الرمز، والرمز مربوط بالبريد الذي أُرسل إليه
 * فلا يصلح بعد تغييره. والتحقق داخل قفل لكل مستخدم، فلا تتجاوز طلبات متزامنة حد المحاولات. والتوثيق نفسه في
 * User::markEmailAsVerified: بريد موثق في حساب آخر لا يُوثَّق ثانية.
 */
class VerifyEmailCode
{
    public function handle(User $user, string $code): void
    {
        try {
            Cache::lock('email-otp-verify:'.$user->id, 10)
                ->block((int) config('security.otp_lock_wait_seconds'), fn () => $this->check($user, $code));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['code' => __('account.phone.busy')]);
        }

        if (! $user->markEmailAsVerified()) {
            throw ValidationException::withMessages(['code' => __('account.email_code.taken')]);
        }

        event(new Verified($user));
    }

    private function check(User $user, string $code): void
    {
        $key = SendEmailVerificationCode::cacheKey($user);
        $entry = Cache::get($key);

        if (! is_array($entry)
            || $user->email === null
            || $entry['email'] !== $user->email
            || $entry['attempts'] >= SendEmailVerificationCode::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw ValidationException::withMessages(['code' => __('account.phone.expired')]);
        }

        if (! hash_equals($entry['hash'], SendEmailVerificationCode::hash($code, $user->email))) {
            $entry['attempts']++;
            // لا تمديد لمدة الرمز مع المحاولات الخاطئة
            Cache::put($key, $entry, Carbon::createFromTimestamp($entry['expires_at']));

            throw ValidationException::withMessages(['code' => __('account.phone.invalid')]);
        }

        Cache::forget($key);
    }
}
