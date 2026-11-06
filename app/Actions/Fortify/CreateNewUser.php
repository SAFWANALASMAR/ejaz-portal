<?php

namespace App\Actions\Fortify;

use App\Actions\Accounts\ResetPasswordByPhone;
use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\RegistrationAttemptOnAccount;
use App\Notifications\RegistrationCapReached;
use App\Rules\SaudiMobile;
use App\Support\AccountSettings;
use App\Support\NetworkPrefix;
use App\Support\SaudiPhone;
use App\Support\SchoolNetworkLimits;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * تسجيل حساب جديد للمستفيد: الاسم الكامل والجوال وكلمة المرور، والبريد اختياري، ولا اسم مستخدم للحساب الجديد (قرار صفوان،
 * 5 نوفمبر 2026 — decisions/2026-11-05-phone-login.md). والجوال فريد على كل الحسابات، فهو معرّف الدخول. كل حساب جديد
 * يأخذ الدور member فقط.
 *
 * الحدود (config/security.php — قرار صفوان، 20 أكتوبر 2026): لكل مشترك ولكل جهاز في الساعة، ولكل شبكة في اليوم،
 * وللبوابة كلها في الساعة بتنبيه المشرف العام والمسؤول التقني. وفوقها التحدي ضد الآليات (VerifyBotChallenge).
 *
 * والبريد أو الجوال المستخدم من قبل برسالة عامة واحدة لا تسمّي الحقل، ويُنبَّه صاحب البريد المسجل بالمحاولة، فلا يكشف
 * التسجيل أي الحقلين يخص حساباً، ويعرف صاحب الحساب بمن يجرّب بريده (decisions.md — 20 أكتوبر 2026). والمحاولة تُحسب
 * من الحدود نفسها، فلا يُعدَّد بها بلا سقف.
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public const DEVICE_COOKIE = 'ejaz_device';

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function create(array $input): User
    {
        $limits = self::limits();

        foreach ($limits as $key => [$max, , $message]) {
            if (RateLimiter::tooManyAttempts($key, $max)) {
                if ($key === 'register:all') {
                    self::warnCapReached($max);
                }

                throw ValidationException::withMessages(['phone' => __($message)]);
            }
        }

        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? ''))) ?: null;
        $phone = is_string($input['phone'] ?? null) && trim($input['phone']) !== '' ? $input['phone'] : null;
        $input['phone'] = $phone === null ? null : (SaudiPhone::normalize($phone) ?? $phone);

        // فريد على كل الحسابات، موثقة أو لا، فهو معرّف الدخول (decisions/2026-11-05-phone-login.md). إلا والتوثيق برسالة مفعّل:
        // رقم يحمله حساب غير موثق يُقبل معلّقاً، فلا يصير لصاحبه إلا بعد الرمز، فيُنزع حينها من الحساب غير الموثق (VerifyPhoneCode)
        $claimable = AccountSettings::verificationEnabled() && ResetPasswordByPhone::available();

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', User::emailTakenRule()],
            'phone' => ['required', 'string', new SaudiMobile, $claimable ? Rule::unique(User::class)->whereNotNull('phone_verified_at') : Rule::unique(User::class)],
            'password' => $this->passwordRules(
                is_string($input['name'] ?? null) ? $input['name'] : null,
                $input['email'],
                $input['phone'],
            ),
            'privacy' => ['accepted'],
        ], [
            'email.unique' => __('account.register.unavailable'),
            'phone.unique' => __('account.register.unavailable'),
        ]);

        if ($validator->fails()) {
            $taken = $validator->failed();

            if (isset($taken['email']['Unique']) || isset($taken['phone']['Unique'])) {
                // تُحسب في حدود المشترك والجهاز والشبكة، لا في سقف البوابة: وإلا أوقف من يجرّب بُرُداً مستخدمة التسجيلَ للجميع
                self::hit(array_diff_key($limits, ['register:all' => true]));
                self::noticeOwner($input['email'], $input['phone']);
                self::mergeTaken($validator, $input['email'] !== null ? 'email' : 'phone');
            }

            // التحقق ثانيةً في طريق الفشل وحده، فيطبّق خطاف mergeTaken ويرمي الأخطاء. والناجح لا يُعاد تحققه: كان validate() بعد fails()
            // يعيد القواعد كلها، ومنها فحص التسريب بطلب خارجي، فيدفع كل تسجيل ناجح زمنه مرتين (بطء الدخول والتسجيل، 5 نوفمبر 2026)
            $validator->validate();
        }

        self::hit($limits);

        $held = $claimable && User::query()->where('phone', $input['phone'])->exists();

        $user = new User([
            'name' => trim((string) $input['name']),
            'email' => $input['email'],
            'phone' => $held ? null : $input['phone'],
            'password' => $input['password'],
            'privacy_accepted_at' => now(),
        ]);
        // التسجيل الذاتي وحده يُحذف حسابه إن تُرك غير موثق (PruneUnverifiedAccounts)، لا حساب أنشأه مشرف
        $user->self_registered_at = now();
        $user->pending_phone = $held ? $input['phone'] : null;

        try {
            $user->save();
        } catch (UniqueConstraintViolationException) {
            // تسجيلان متزامنان بالرقم نفسه: الثاني يرى الرسالة العامة نفسها لا خطأ 500
            throw ValidationException::withMessages([$input['email'] !== null ? 'email' : 'phone' => __('account.register.unavailable')]);
        }

        $user->assignRole(RoleName::Member->value);

        return $user;
    }

    /**
     * مفاتيح الحدود: [الحد، المدة بالثواني، رسالة البلوغ]. والشبكة للعنوان العام وحده.
     *
     * @return array<string, array{0: int, 1: int, 2: string}>
     */
    private static function limits(): array
    {
        $ip = NetworkPrefix::normalize(request()->ip());
        $subscriber = NetworkPrefix::subscriber($ip);

        $limits = [
            'register:'.$subscriber => [SchoolNetworkLimits::limit('registration_per_ip_per_hour'), 3600, 'account.register.too_many'],
            'register-device:'.self::device() => [SchoolNetworkLimits::limit('registration_per_device_per_hour'), 3600, 'account.register.too_many'],
            'register:all' => [(int) config('security.registration_per_hour'), 3600, 'account.register.busy'],
        ];

        if ($ip !== null && NetworkPrefix::isPublic($ip)) {
            $limits['register-net:'.NetworkPrefix::prefix($ip, NetworkPrefix::isIpv6($ip) ? 48 : 24)] = [SchoolNetworkLimits::limit('registration_per_network_per_day'), 86400, 'account.register.too_many'];
        }

        return $limits;
    }

    /**
     * معرّف الجهاز: كعكة عشوائية طويلة الأجل (مشفّرة كبقية الكعكات). لا بصمة المتصفح، لأن آلاف المشتركين خلف عنوان شبكة
     * الجوال الواحد يحملون نص المتصفح نفسه (الطراز نفسه)، فيُحجبون معاً. ومن يحذف الكعكة يبقى تحت حد المشترك والشبكة
     * والتحدي ضد الآليات.
     */
    private static function device(): string
    {
        $device = request()->cookie(self::DEVICE_COOKIE);

        if (! is_string($device) || preg_match('/^[a-f0-9]{32}$/', $device) !== 1) {
            $device = bin2hex(random_bytes(16));
            Cookie::queue(Cookie::make(self::DEVICE_COOKIE, $device, 60 * 24 * 365, null, null, null, true, false, 'lax'));
        }

        return $device;
    }

    /**
     * @param  array<string, array{0: int, 1: int, 2: string}>  $limits
     */
    private static function hit(array $limits): void
    {
        foreach ($limits as $key => [, $decay]) {
            RateLimiter::hit($key, $decay);
        }
    }

    /**
     * رسالة واحدة على حقل واحد (البريد، أو الجوال إن لم يُكتب بريد)، فلا يُعرف من مكانها أي الحقلين يخص حساباً.
     */
    private static function mergeTaken(\Illuminate\Validation\Validator $validator, string $field): void
    {
        $validator->after(function (\Illuminate\Validation\Validator $validator) use ($field): void {
            $errors = $validator->errors();

            foreach (['email', 'phone'] as $key) {
                $messages = $errors->get($key);
                $errors->forget($key);

                foreach ($messages as $message) {
                    if ($message !== __('account.register.unavailable')) {
                        $errors->add($key, $message);
                    }
                }
            }

            $errors->add($field, __('account.register.unavailable'));
        });
    }

    /**
     * صاحب البريد أو الجوال المسجل يُنبَّه ببريده بمحاولة التسجيل بهما، مرة في اليوم على الأكثر. ومن لا بريد له لا يُنبَّه
     * حتى يُختار مزود الرسائل.
     */
    private static function noticeOwner(?string $email, ?string $phone): void
    {
        $owner = User::query()
            ->whereNotNull('email')
            // للبريد الموثق وحده: غير الموثق قد يكون بريد غيره كتبه الحامل، فلا يُراسَل (البلاغ 185، L5)
            ->whereNotNull('email_verified_at')
            ->where(fn ($query) => $query->when($email !== null, fn ($query) => $query->where('email', $email))
                ->when($phone !== null, fn ($query) => $query->orWhere('phone', $phone)))
            ->first();

        if ($owner !== null && Cache::add('register-attempt-notice:'.$owner->id, true, 86400)) {
            $owner->notify(new RegistrationAttemptOnAccount);
        }
    }

    private static function warnCapReached(int $cap): void
    {
        if (! Cache::add('register-cap-alert', true, 3600)) {
            return;
        }

        Log::critical('Registrations reached the hourly cap; sign up is paused.', ['cap' => $cap]);

        Notification::send(
            User::role(RoleName::values(RoleName::overseers()))->where('is_active', true)->get(),
            new RegistrationCapReached($cap),
        );
    }
}
