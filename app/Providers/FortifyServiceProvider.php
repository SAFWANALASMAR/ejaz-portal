<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\DisableTwoFactorAuthentication;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Auth\LoginRateLimiter;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use App\Support\LoginIdentifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as BaseDisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\RegisterResponse as RegisterResponseContract;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
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
    }

    public function boot(): void
    {
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::updateUserProfileInformationUsing(UpdateUserProfileInformation::class);
        Fortify::updateUserPasswordsUsing(UpdateUserPassword::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::redirectUserForTwoFactorAuthenticationUsing(RedirectIfTwoFactorAuthenticatable::class);

        Fortify::authenticateUsing(fn (Request $request): ?User => $this->authenticate($request));

        $this->registerViews();
        $this->registerRateLimiters();
    }

    /**
     * الدخول بالبريد أو برقم الجوال. الحساب الموقوف لا يدخل،
     * ورسالة الفشل واحدة في كل الحالات.
     */
    private function authenticate(Request $request): ?User
    {
        $login = LoginIdentifier::normalize($request->input(Fortify::username()));
        $password = (string) $request->input('password');

        $user = match (true) {
            $login === '' => null,
            LoginIdentifier::isEmail($login) => User::query()->where('email', $login)->first(),
            str_starts_with($login, '+966') => User::query()->where('phone', $login)->first(),
            default => null,
        };

        if ($user === null) {
            // زمن استجابة متقارب سواء وُجد الحساب أم لا
            Hash::check($password, '$2y$12$'.str_repeat('a', 53));

            return null;
        }

        if (! $user->is_active || ! Hash::check($password, $user->password)) {
            return null;
        }

        return $user;
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
        RateLimiter::for('two-factor', fn (Request $request): Limit => Limit::perMinute(5)->by('two-factor:'.$request->session()->get('login.id')));
    }
}
