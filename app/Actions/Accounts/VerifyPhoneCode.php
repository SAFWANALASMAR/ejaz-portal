<?php

namespace App\Actions\Accounts;

use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * يتحقق من رمز الجوال. خمس محاولات خاطئة تُبطل الرمز، والرمز مرتبط بالرقم
 * الذي أُرسل إليه فلا يصلح بعد تغيير الرقم.
 *
 * التحقق داخل قفل لكل مستخدم، فلا تتجاوز طلبات متزامنة حد المحاولات.
 */
class VerifyPhoneCode
{
    public function handle(User $user, string $code): void
    {
        $this->consume($user, $code, SendPhoneVerificationCode::VERIFY);

        $this->claimNumber($user);
    }

    /**
     * يستهلك الرمز لغرضه (توثيق الجوال أو استعادة كلمة المرور — ResetPasswordByPhone): صحيح فيُحذف، فلا يصلح مرة ثانية،
     * وخاطئ فتُعدّ المحاولة، والخامسة تبطله. داخل قفل لكل مستخدم وغرض. ورسائل الخطأ بمفاتيح الغرض نفسه.
     *
     * @param  array{expired: string, invalid: string, busy: string}|null  $messages
     *
     * @throws ValidationException
     */
    public function consume(User $user, string $code, string $purpose, ?array $messages = null): void
    {
        $messages ??= ['expired' => __('account.phone.expired'), 'invalid' => __('account.phone.invalid'), 'busy' => __('account.phone.busy')];

        try {
            Cache::lock('phone-otp-verify:'.($purpose === SendPhoneVerificationCode::VERIFY ? '' : $purpose.':').$user->id, 10)
                ->block((int) config('security.otp_lock_wait_seconds'), fn () => $this->check($user, $code, $purpose, $messages));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['code' => $messages['busy']]);
        }
    }

    /**
     * @param  array{expired: string, invalid: string, busy: string}  $messages
     */
    private function check(User $user, string $code, string $purpose, array $messages): void
    {
        $key = SendPhoneVerificationCode::cacheKey($user, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry)
            || $entry['phone'] !== $user->phone
            || $entry['attempts'] >= SendPhoneVerificationCode::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw ValidationException::withMessages(['code' => $messages['expired']]);
        }

        if (! hash_equals($entry['hash'], SendPhoneVerificationCode::hash($code, (string) $user->phone))) {
            $entry['attempts']++;
            // لا تمديد لمدة الرمز مع المحاولات الخاطئة
            Cache::put($key, $entry, Carbon::createFromTimestamp($entry['expires_at']));

            throw ValidationException::withMessages(['code' => $messages['invalid']]);
        }

        Cache::forget($key);

        // الرمز المكتمل يحرر مكانه في حد الشبكة
        SendPhoneVerificationCode::releaseBuckets((array) ($entry['buckets'] ?? []));
    }

    /**
     * الرقم الموثق لحساب واحد (فهرس تفرد جزئي)، والحسابات غير الموثقة التي تحمله تفقده، فلا يحجز
     * حساب غير موثق رقم صاحبه (decisions.md — 14 أكتوبر 2026). ورقم موثق في حساب آخر لا يُوثَّق ثانية.
     */
    private function claimNumber(User $user): void
    {
        try {
            DB::transaction(function () use ($user): void {
                $holders = User::query()->where('phone', $user->phone)->whereKeyNot($user->getKey());

                if ((clone $holders)->whereNotNull('phone_verified_at')->exists()) {
                    throw ValidationException::withMessages(['code' => __('account.phone.taken')]);
                }

                $holders->whereNull('phone_verified_at')->update(['phone' => null]);

                $user->forceFill(['phone_verified_at' => now()])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // حسابان وثّقا الرقم نفسه في اللحظة نفسها
            throw ValidationException::withMessages(['code' => __('account.phone.taken')]);
        }
    }
}
