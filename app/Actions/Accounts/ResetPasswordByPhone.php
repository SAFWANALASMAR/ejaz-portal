<?php

namespace App\Actions\Accounts;

use App\Actions\Fortify\ResetUserPassword;
use App\Models\User;
use App\Services\Messaging\MessagingSettings;
use App\Support\LoginIdentifier;
use App\Support\PasswordPolicy;
use App\Support\SaudiPhone;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * تعيين كلمة مرور جديدة برمز الجوال (قرار صفوان، 20 أكتوبر 2026): الرمز ستة أرقام، صالح عشر دقائق، لمرة واحدة، ويُبطل
 * بعد خمس محاولات خاطئة (VerifyPhoneCode::consume). والرمز يُفحص أولاً، ثم كلمة المرور بالسياسة، ثم يُستهلك الرمز:
 * فلا يرى رد كلمة المرور إلا من يملك الرمز، ولا يضيع الرمز بكلمة مرور مرفوضة.
 *
 * بعد التعيين: ينبَّه صاحب الحساب (ResetUserPassword)، ويتجدد رمز «تذكّرني»، وتنتهي جلساته الأخرى ببصمة كلمة المرور
 * (AuthenticateSession)، ولا دخول تلقائي، فيبقى التحقق الثنائي على حسابات اللوحة. والرقم بلا حساب يرد الرسائل نفسها
 * بعدّاد محاولاته، فلا يكشف الرد وجود الحساب.
 */
class ResetPasswordByPhone
{
    public function __construct(private VerifyPhoneCode $verify, private ResetUserPassword $reset) {}

    /**
     * يظهر خيار الجوال متى كانت الرسائل النصية جاهزة من اللوحة. وفي التطوير والاختبار وحدهما يعمل بمشغّل log، لا في أي
     * بيئة غير الإنتاج: فالخادم التجريبي العام كالإنتاج (مراجعة المشرف على #64).
     */
    public static function available(): bool
    {
        return MessagingSettings::ready('sms') || MessagingSettings::otpViaWhatsApp() || app()->environment('local', 'testing');
    }

    /**
     * @param  array<string, mixed>  $input  code وpassword وpassword_confirmation
     *
     * @throws ValidationException
     */
    public function handle(string $phone, array $input): void
    {
        Validator::make($input, ['code' => ['required', 'string', 'regex:/^\d{6}$/']], [
            'code.regex' => __('account.password_reset.invalid'),
        ])->validate();

        $user = User::query()
            ->where('phone', SaudiPhone::normalize($phone))
            ->whereNotNull('phone_verified_at')
            ->where('is_active', true)
            ->first();

        $messages = [
            'expired' => __('account.password_reset.expired'),
            'invalid' => __('account.password_reset.invalid'),
            'busy' => __('account.phone.busy'),
        ];

        if ($user === null) {
            $this->refuseUnknown($phone, $messages);
        }

        // الرمز أولاً ثم كلمة المرور: لا يصل إلى قواعدها (الاسم والبريد وفحص التسريب الخارجي) إلا من يملك الرمز، فلا
        // يفرق ردها ولا توقيتها بين رقم مسجل وغيره. وكلمة المرور المرفوضة لا تستهلك الرمز ولا تعدّ محاولة
        $this->verify->consume($user, (string) $input['code'], SendPhoneVerificationCode::RESET, $messages,
            function () use ($input, $user): void {
                // بعد إعادة ضبط التحقق الثنائي من الإدارة تُعيَّن كلمة المرور برابط البريد الموثق وحده (قرار المشرف، 3 نوفمبر
                // 2026 — ResetTwoFactorByAdmin)، فمن ادّعى فقد جواله لا يعيّنها برمز جوال بيده. ولا يصل هذا الرد إلا من يملك الرمز
                if ($user->must_set_password_at !== null) {
                    throw ValidationException::withMessages(['code' => __('users.reset_two_factor.phone_reset_refused')]);
                }

                Validator::make($input, ['password' => PasswordPolicy::rules($user->name, $user->email, $user->phone, username: $user->username)])->validate();
            });

        $this->reset->reset($user, [
            'password' => (string) $input['password'],
            'password_confirmation' => (string) ($input['password_confirmation'] ?? ''),
        ]);

        $user->setRememberToken(Str::random(60));
        $user->save();

        event(new PasswordReset($user));
    }

    /**
     * رقم بلا حساب موثق: خطأ «الرمز غير صحيح» كالحساب الموجود، وبعد خمس محاولات «انتهى»، فلا يفرق الرد بين الحالين.
     *
     * @param  array{expired: string, invalid: string, busy: string}  $messages
     *
     * @throws ValidationException
     */
    private function refuseUnknown(string $phone, array $messages): never
    {
        $key = 'password-reset-unknown:'.LoginIdentifier::hash((string) SaudiPhone::normalize($phone));

        if (RateLimiter::tooManyAttempts($key, SendPhoneVerificationCode::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages(['code' => $messages['expired']]);
        }

        RateLimiter::hit($key, SendPhoneVerificationCode::TTL_MINUTES * 60);

        throw ValidationException::withMessages(['code' => $messages['invalid']]);
    }
}
