<?php

namespace App\Actions\Fortify;

use App\Enums\RoleName;
use App\Models\User;
use App\Notifications\RegistrationAttemptOnAccount;
use App\Notifications\RegistrationCapReached;
use App\Rules\SaudiMobile;
use App\Support\NetworkPrefix;
use App\Support\SaudiPhone;
use App\Support\SchoolNetworkLimits;
use App\Support\Username;
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
 * تسجيل حساب جديد للمستفيد: الاسم الكامل واسم المستخدم وكلمة المرور، والبريد والجوال اختياريان (ملاحظات المدير التنفيذي
 * عبر صفوان، 25 أكتوبر 2026). كل حساب جديد يأخذ الدور member فقط.
 *
 * الحدود (config/security.php — قرار صفوان، 20 أكتوبر 2026): لكل مشترك ولكل جهاز في الساعة، ولكل شبكة في اليوم،
 * وللبوابة كلها في الساعة بتنبيه المشرف العام والمسؤول التقني. وفوقها التحدي ضد الآليات (VerifyBotChallenge).
 *
 * والبريد أو الجوال المستخدم من قبل برسالة عامة واحدة لا تسمّي الحقل، ويُنبَّه صاحب البريد المسجل بالمحاولة، فلا يكشف
 * التسجيل أي الحقلين يخص حساباً، ويعرف صاحب الحساب بمن يجرّب بريده (decisions.md — 20 أكتوبر 2026). والمحاولة تُحسب
 * من الحدود نفسها، فلا يُعدَّد بها بلا سقف.
 *
 * واسم المستخدم المحجوز يُقال صراحة على حقله: لا يُختار اسم آخر بدونه، وهو معرّف يختاره صاحبه لا بيانات شخصية. ومحاولاته
 * تُحسب من الحدود نفسها، فلا يُعدَّد به بلا سقف (decisions.md — 25 أكتوبر 2026).
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

                throw ValidationException::withMessages(['username' => __($message)]);
            }
        }

        $input['username'] = Username::normalize(is_string($input['username'] ?? null) ? $input['username'] : null);
        $input['email'] = mb_strtolower(trim((string) ($input['email'] ?? ''))) ?: null;
        $phone = is_string($input['phone'] ?? null) && trim($input['phone']) !== '' ? $input['phone'] : null;
        $input['phone'] = $phone === null ? null : (SaudiPhone::normalize($phone) ?? $phone);

        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'min:3', 'max:120'],
            'username' => [...Username::rules(), Rule::unique(User::class, 'username')],
            'email' => ['nullable', 'string', 'email:rfc', 'max:255', User::emailTakenRule()],
            // التفرد على الأرقام الموثقة وحدها، فلا يحجز حساب غير موثق رقم غيره (decisions.md — 14 أكتوبر 2026)
            'phone' => ['nullable', 'string', new SaudiMobile, Rule::unique(User::class)->whereNotNull('phone_verified_at')],
            'password' => $this->passwordRules(
                is_string($input['name'] ?? null) ? $input['name'] : null,
                $input['email'],
                $input['phone'],
                $input['username'],
            ),
            'privacy' => ['accepted'],
        ], [
            'username.regex' => __('account.register.username_format'),
            'username.unique' => __('account.register.username_taken'),
            'email.unique' => __('account.register.unavailable'),
            'phone.unique' => __('account.register.unavailable'),
        ]);

        if ($validator->fails()) {
            $taken = $validator->failed();

            if (isset($taken['username']['Unique']) || isset($taken['email']['Unique']) || isset($taken['phone']['Unique'])) {
                // تُحسب في حدود المشترك والجهاز والشبكة، لا في سقف البوابة: وإلا أوقف من يجرّب بُرُداً مستخدمة التسجيلَ للجميع
                self::hit(array_diff_key($limits, ['register:all' => true]));
            }

            if (isset($taken['email']['Unique']) || isset($taken['phone']['Unique'])) {
                self::noticeOwner($input['email']);
                self::mergeTaken($validator, $input['email'] !== null ? 'email' : 'phone');
            }
        }

        $validator->validate();

        self::hit($limits);

        $user = new User([
            'name' => trim((string) $input['name']),
            'username' => $input['username'],
            'email' => $input['email'],
            'phone' => $input['phone'],
            'password' => $input['password'],
            'privacy_accepted_at' => now(),
        ]);
        // التسجيل الذاتي وحده يُحذف حسابه إن تُرك غير موثق (PruneUnverifiedAccounts)، لا حساب أنشأه مشرف
        $user->self_registered_at = now();
        $user->save();

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
     * صاحب البريد المسجل يُنبَّه بمحاولة التسجيل ببريده، مرة في اليوم على الأكثر.
     */
    private static function noticeOwner(?string $email): void
    {
        if ($email === null) {
            return;
        }

        $owner = User::query()->where('email', $email)->first();

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
