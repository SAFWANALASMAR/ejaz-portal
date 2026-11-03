<?php

namespace App\Actions\Users;

use App\Auth\CurrentPasswordLimiter;
use App\Enums\ActivityEvent;
use App\Models\User;
use App\Notifications\TwoFactorResetByAdmin;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\Passwords\PasswordBroker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * إعادة ضبط التحقق الثنائي من اللوحة لمن فقد تطبيق المصادقة ورموز الاسترداد (قرار صفوان (أ)، 3 نوفمبر 2026 —
 * docs/decisions/2026-11-03-2fa-admin-reset.md). لمديري النظام بقيود الحساب في UserPolicy::resetTwoFactor، وبكلمة مرور
 * المنفّذ (قفلها مشترك مع تأكيد كلمة المرور — CurrentPasswordLimiter) وسبب مكتوب، وخمس مرات في الساعة لكل منفّذ.
 *
 * يُحذف السر ورموز الاسترداد وتاريخ التأكيد، ويتجدد رمز «تذكّرني»، وتنتهي كل جلسات الحساب عند طلبها التالي أياً كان
 * مخزنها (sessions_revoked_at — EnsureAccountIsActive)، في معاملة واحدة مع سجل العمليات: المنفّذ والحساب والسبب، بلا سر ولا
 * رمز. ثم يُبلَّغ صاحب الحساب بالبريد في الطابور بعد اعتماد المعاملة. ومن يشترط دوره التحقق الثنائي يُحوَّل إلى تفعيله قبل
 * اللوحة (EnsureTwoFactorIsEnabled).
 *
 * وتُبطَل كلمة المرور معه (قرار المشرف بعد المراجعة الأمنية، 3 نوفمبر 2026): من عرف كلمة مرور غيره وادّعى فقد جواله لا
 * يدخل بها بعد إعادة الضبط. تُستبدل بتجزئة عشوائية لا يعرفها أحد، ويُعلَّم الحساب must_set_password_at، ويُرسل رابط
 * تعيين كلمة المرور (وسيط الاستعادة نفسه ومدته) إلى البريد الموثق وحده. فلا دخول ولا تفعيل لتطبيق المصادقة حتى تُعيَّن
 * برابط البريد (ResetUserPassword يرفع العلامة). والحساب بلا بريد موثق لا يُعاد ضبطه.
 */
class ResetTwoFactorByAdmin
{
    public const MAX_PER_HOUR = 5;

    public const REASON_MAX = 255;

    public const VIA_CONSOLE = 'console';

    /**
     * قواعد المدخلات. ونموذج اللوحة يطابقها (الإلزام والحد REASON_MAX)، وهذه هي الحاكمة لكل مستدعٍ.
     *
     * @return array<string, list<string>>
     */
    public static function rules(): array
    {
        return [
            'password' => ['required', 'string'],
            'reason' => ['required', 'string', 'max:'.self::REASON_MAX],
        ];
    }

    /**
     * @param  array<string, mixed>  $input  password وreason
     *
     * @throws AuthorizationException
     * @throws ValidationException مدخل ناقص، أو كلمة مرور خاطئة، أو تجاوز الحد
     */
    public function handle(User $actor, User $user, array $input): void
    {
        if (! $actor->can('resetTwoFactor', $user)) {
            throw new AuthorizationException;
        }

        self::ensureVerifiedEmail($user);

        $data = Validator::make($input, self::rules(), [], [
            'password' => __('users.reset_two_factor.password'),
            'reason' => __('users.reset_two_factor.reason'),
        ])->validate();

        $key = self::limiterKey($actor);

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            self::throttled($key);
        }

        CurrentPasswordLimiter::ensureNotLocked($actor, 'password');

        if (! Hash::check((string) $data['password'], $actor->password)) {
            CurrentPasswordLimiter::failed($actor);

            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        CurrentPasswordLimiter::passed($actor);

        // العدّ بزيادة ذرية تُقارن قيمتها، فلا تتجاوز الطلبات المتزامنة الحد
        if (RateLimiter::increment($key, 3600) > self::MAX_PER_HOUR) {
            self::throttled($key);
        }

        self::apply($user, $actor, (string) $data['reason']);
    }

    /**
     * التنفيذ نفسه بعد الفحوص، مشتركاً بين اللوحة وأمر الطرفية (ejaz:reset-two-factor). والمنفّذ null للطرفية، فيُسجَّل
     * «via: console» ويظهر منفّذه «الطرفية» في سجل العمليات.
     */
    public static function apply(User $user, ?User $actor, string $reason): void
    {
        self::ensureVerifiedEmail($user);

        $token = DB::transaction(function () use ($actor, $user, $reason): string {
            $user->forceFill([
                // كلمة المرور القديمة لا تعود تصلح، ولا يعرف أحد الجديدة حتى تُعيَّن برابط البريد
                'password' => Hash::make(Str::random(40)),
                'must_set_password_at' => now(),
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
                'remember_token' => Str::random(60),
                'sessions_revoked_at' => now(),
            ])->save();

            $log = activity('security')
                ->performedOn($user)
                ->event(ActivityEvent::TwoFactorResetByAdmin->value)
                ->withProperties([
                    'reason' => trim($reason),
                    ...($actor === null ? ['via' => self::VIA_CONSOLE] : []),
                ]);

            if ($actor !== null) {
                $log->causedBy($actor);
            }

            $log->log(ActivityEvent::TwoFactorResetByAdmin->value);

            /** @var PasswordBroker $broker */
            $broker = Password::broker();

            return $broker->createToken($user);
        });

        $user->notify(new TwoFactorResetByAdmin($token));
    }

    /**
     * رابط تعيين كلمة المرور لا يُرسل إلا إلى بريد موثق، فغير الموثق قد يحمله غير صاحبه. وبلا رابط لا يُعاد الضبط.
     *
     * @throws ValidationException
     */
    public static function ensureVerifiedEmail(User $user): void
    {
        if (! $user->hasVerifiedEmail()) {
            throw ValidationException::withMessages(['reason' => __('users.reset_two_factor.no_verified_email')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private static function throttled(string $key): never
    {
        throw ValidationException::withMessages([
            'reason' => __('users.reset_two_factor.throttled', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
        ]);
    }

    private static function limiterKey(User $actor): string
    {
        return 'reset-two-factor:'.$actor->id;
    }
}
