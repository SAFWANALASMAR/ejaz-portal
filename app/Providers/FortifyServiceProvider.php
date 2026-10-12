<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\DisableTwoFactorAuthentication;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Auth\LoginRateLimiter;
use App\Enums\RoleName;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Http\Responses\PortalLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use App\Support\NetworkPrefix;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as BaseDisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\TwoFactorLoginResponse as TwoFactorLoginResponseContract;
use Laravel\Fortify\Fortify;
use Laravel\Fortify\LoginRateLimiter as FortifyLoginRateLimiter;

/**
 * الدخول الموحد للموقع ولوحة الإدارة عبر Fortify — انظر docs/decisions.md.
 */
class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // رسالة واحدة لطلب الاستعادة سواء وُجد البريد أم لا
        $this->app->bind(FailedPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
        $this->app->bind(SuccessfulPasswordResetLinkRequestResponse::class, NeutralPasswordResetLinkResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->bind(BaseDisableTwoFactorAuthentication::class, DisableTwoFactorAuthentication::class);
        $this->app->bind(FortifyLoginRateLimiter::class, LoginRateLimiter::class);
        // مدخل كل بوابة بعد الدخول: اللوحة لمن يدخلها، ولوحة المستخدم لغيره
        $this->app->singleton(LoginResponseContract::class, PortalLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, PortalLoginResponse::class);
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(fn (Request $request): ?User => $this->authenticate($request));
        Fortify::confirmPasswordsUsing(fn (User $user, ?string $password): bool => $this->confirmPassword($user, $password));

        $this->registerViews();
        $this->registerRateLimiters();
    }

    /**
     * الدخول بالبريد أو برقم الجوال. الحساب الموقوف لا يدخل،
     * ورسالة الفشل واحدة في كل الحالات.
     */
    private function authenticate(Request $request): ?User
    {
        $password = (string) $request->input('password');

        $user = User::findByLoginIdentifier($request->input(Fortify::username()));

        if ($user === null) {
            // زمن استجابة متقارب سواء وُجد الحساب أم لا، بتكلفة التجزئة المضبوطة نفسها
            Hash::check($password, sprintf('$2y$%02d$%s', (int) config('hashing.bcrypt.rounds', 12), str_repeat('a', 53)));

            return null;
        }

        if (! $user->is_active || ! Hash::check($password, $user->password)) {
            return null;
        }

        // لا «تذكّرني» للأدوار الإدارية: كعكة التذكّر تعيد الدخول بلا كلمة مرور ولا تحقق ثنائي بعد انتهاء
        // الجلسة، فتتجاوز مهلة خمول اللوحة (docs/security/2026-10-10-security-audit.md)
        if ($user->requiresTwoFactor() || $user->hasAnyRole(RoleName::values(RoleName::panelRoles()))) {
            $request->merge(['remember' => false]);
        }

        return $user;
    }

    /**
     * تأكيد كلمة المرور قبل الصفحات الحساسة: الأمان والتحقق الثنائي، وتنزيل البيانات.
     * تأكيد Fortify الافتراضي يبحث بعمود اسم الدخول (login)، ولا عمود بهذا الاسم لأن
     * الدخول بالبريد أو الجوال. وخمس محاولات خاطئة تقفله ربع ساعة، كقفل الدخول.
     */
    private function confirmPassword(User $user, ?string $password): bool
    {
        $key = 'confirm-password:'.$user->id;

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'password' => __('account.confirm_password.throttle', ['minutes' => (int) ceil(RateLimiter::availableIn($key) / 60)]),
            ]);
        }

        if ($user->is_active && Hash::check((string) $password, $user->password)) {
            RateLimiter::clear($key);

            return true;
        }

        RateLimiter::hit($key, 900);

        return false;
    }

    private function registerViews(): void
    {
        Fortify::loginView(fn () => view('auth.login'));
        Fortify::registerView(fn () => view('auth.register'));
        Fortify::requestPasswordResetLinkView(fn () => view('auth.forgot-password'));
        Fortify::resetPasswordView(fn (Request $request) => view('auth.reset-password', ['request' => $request]));
        Fortify::verifyEmailView(fn () => view('auth.verify-email'));
        Fortify::confirmPasswordView(fn () => view('auth.confirm-password'));
        Fortify::twoFactorChallengeView(fn () => view('auth.two-factor-challenge'));
    }

    /**
     * قفل الدخول في App\Auth\LoginRateLimiter: المحاولات الخاطئة فقط، بالمعرّف الموحَّد.
     * والتحقق الثنائي خمس محاولات في الدقيقة.
     */
    private function registerRateLimiters(): void
    {
        // خمس محاولات في الدقيقة، ومعها سقفان أطول حتى لا يُخمَّن الرمز على مدى أيام بكلمة مرور مسرّبة
        RateLimiter::for('two-factor', function (Request $request): array {
            $key = 'two-factor:'.$request->session()->get('login.id');

            return [
                Limit::perMinute(5)->by($key),
                Limit::perMinutes(15, 10)->by($key.':quarter'),
                Limit::perDay(30)->by($key.':day'),
            ];
        });

        // «تواصل معنا»: لكل مشترك (عنوان IPv4، أو /64 في IPv6 فلا يُلتف عليه بتدوير العناوين)، ولكل
        // موقع /48 في IPv6، وسقف عام للبوابة حارساً أخيراً. والأرقام تتسع للشبكات المشتركة (CGNAT)
        RateLimiter::for('contact', function (Request $request): array {
            $ip = NetworkPrefix::normalize($request->ip());

            $limits = [
                Limit::perHour((int) config('security.contact_per_subscriber_per_hour'))->by('contact:'.NetworkPrefix::subscriber($ip)),
                Limit::perHour((int) config('security.contact_per_hour'))->by('contact:all'),
            ];

            if ($ip !== null && NetworkPrefix::isIpv6($ip)) {
                $limits[] = Limit::perHour((int) config('security.contact_per_ipv6_site_per_hour'))->by('contact:'.NetworkPrefix::prefix($ip, 48));
            }

            return $limits;
        });
    }
}
