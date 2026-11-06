<?php

namespace App\Actions\Accounts;

use App\Actions\Users\ReleasePhone;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
     * و$beforeConsume يجري بعد ثبوت صحة الرمز وقبل استهلاكه، داخل القفل: ما يرميه يُبقي الرمز صالحاً بلا عدّ محاولة.
     * فالاستعادة تفحص كلمة المرور فيه، فلا يرى رد كلمة المرور إلا من يملك الرمز.
     *
     * @param  array{expired: string, invalid: string, busy: string}|null  $messages
     * @param  (callable(): void)|null  $beforeConsume
     *
     * @throws ValidationException
     */
    public function consume(User $user, string $code, string $purpose, ?array $messages = null, ?callable $beforeConsume = null): void
    {
        $messages ??= ['expired' => __('account.phone.expired'), 'invalid' => __('account.phone.invalid'), 'busy' => __('account.phone.busy')];

        try {
            Cache::lock('phone-otp-verify:'.($purpose === SendPhoneVerificationCode::VERIFY ? '' : $purpose.':').$user->id, 10)
                ->block((int) config('security.otp_lock_wait_seconds'), fn () => $this->check($user, $code, $purpose, $messages, $beforeConsume));
        } catch (LockTimeoutException) {
            throw ValidationException::withMessages(['code' => $messages['busy']]);
        }
    }

    /**
     * @param  array{expired: string, invalid: string, busy: string}  $messages
     * @param  (callable(): void)|null  $beforeConsume
     */
    private function check(User $user, string $code, string $purpose, array $messages, ?callable $beforeConsume): void
    {
        $key = SendPhoneVerificationCode::cacheKey($user, $purpose);
        $entry = Cache::get($key);
        $phone = $purpose === SendPhoneVerificationCode::VERIFY ? $user->phoneToVerify() : $user->phone;

        if (! is_array($entry)
            || $entry['phone'] !== $phone
            || $entry['attempts'] >= SendPhoneVerificationCode::MAX_ATTEMPTS) {
            Cache::forget($key);

            throw ValidationException::withMessages(['code' => $messages['expired']]);
        }

        if (! hash_equals($entry['hash'], SendPhoneVerificationCode::hash($code, (string) $phone))) {
            $entry['attempts']++;
            // لا تمديد لمدة الرمز مع المحاولات الخاطئة
            Cache::put($key, $entry, Carbon::createFromTimestamp($entry['expires_at']));

            throw ValidationException::withMessages(['code' => $messages['invalid']]);
        }

        if ($beforeConsume !== null) {
            $beforeConsume();
        }

        Cache::forget($key);

        // الرمز المكتمل يحرر مكانه في حد الشبكة
        SendPhoneVerificationCode::releaseBuckets((array) ($entry['buckets'] ?? []));
    }

    /**
     * الرقم لحساب واحد (فهرس تفرد — decisions/2026-11-05-phone-login.md). ومن سجّل برقم يحمله حساب غير موثق (pending_phone)
     * يأخذه بالرمز: يُنزع من ذلك الحساب بسجل عملية وتنبيه (ReleasePhone)، فلا يحجز حساب غير موثق رقم صاحبه. ورقم موثق في
     * حساب آخر لا يُوثَّق ثانية.
     */
    private function claimNumber(User $user): void
    {
        $phone = $user->phoneToVerify();
        $staff = null;

        try {
            DB::transaction(function () use ($user, $phone, &$staff): void {
                $holder = User::query()->where('phone', $phone)->whereKeyNot($user->getKey())->lockForUpdate()->first();

                if ($holder !== null && $holder->phone_verified_at !== null) {
                    throw ValidationException::withMessages(['code' => __('account.phone.taken')]);
                }

                // ولا يُنزع رقم حساب له دور في اللوحة بالرمز: قد يكون رقم موظف لم يوثّقه، فيحرره مدير النظام بعد التحقق
                // (البلاغ 185، L6)
                if ($holder !== null && $holder->hasAnyRole(RoleName::values(RoleName::panelRoles()))) {
                    $staff = $holder;

                    return;
                }

                if ($holder !== null) {
                    ReleasePhone::release($holder, $user);
                }

                $user->forceFill(['phone' => $phone, 'pending_phone' => null, 'phone_verified_at' => now()])->save();
            });
        } catch (UniqueConstraintViolationException) {
            // حسابان وثّقا الرقم نفسه في اللحظة نفسها
            throw ValidationException::withMessages(['code' => __('account.phone.taken')]);
        }

        if ($staff !== null) {
            // للمسؤول في سجل العمليات وسجل الخادم، بلا الرقم
            activity('security')->causedBy($user)->performedOn($staff)
                ->event(ActivityEvent::PhoneClaimRefused->value)->log(ActivityEvent::PhoneClaimRefused->value);
            Log::warning('A verification code tried to take the unverified phone of a staff account.', ['holder' => $staff->id, 'claimant' => $user->id]);

            throw ValidationException::withMessages(['code' => __('account.phone.taken')]);
        }
    }
}
