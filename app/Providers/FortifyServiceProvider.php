<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\DisableTwoFactorAuthentication;
use App\Actions\Fortify\EnableTwoFactorAuthentication;
use App\Actions\Fortify\ResetUserPassword;
use App\Actions\Fortify\UpdateUserPassword;
use App\Actions\Fortify\UpdateUserProfileInformation;
use App\Auth\CurrentPasswordLimiter;
use App\Auth\LoginRateLimiter;
use App\Enums\RoleName;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureTwoFactorIsEnabled;
use App\Http\Responses\NeutralPasswordResetLinkResponse;
use App\Http\Responses\NeutralPasswordResetResponse;
use App\Http\Responses\PortalLoginResponse;
use App\Http\Responses\RegisterResponse;
use App\Models\User;
use App\Support\NetworkPrefix;
use App\Support\PasswordPolicy;
use App\Support\SchoolNetworkLimits;
use App\Support\UploadLimits;
use Illuminate\Auth\Events\Login;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication as BaseDisableTwoFactorAuthentication;
use Laravel\Fortify\Actions\EnableTwoFactorAuthentication as BaseEnableTwoFactorAuthentication;
use Laravel\Fortify\Actions\RedirectIfTwoFactorAuthenticatable;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
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
        // ورسالة واحدة لرمز خاطئ في تعيين كلمة المرور الجديدة، للبريد المسجل وغيره
        $this->app->bind(FailedPasswordResetResponse::class, NeutralPasswordResetResponse::class);
        $this->app->singleton(RegisterResponseContract::class, RegisterResponse::class);
        $this->app->bind(BaseDisableTwoFactorAuthentication::class, DisableTwoFactorAuthentication::class);
        $this->app->bind(BaseEnableTwoFactorAuthentication::class, EnableTwoFactorAuthentication::class);
        $this->app->bind(FortifyLoginRateLimiter::class, LoginRateLimiter::class);
        // مدخل كل بوابة بعد الدخول: اللوحة لمن يدخلها، ولوحة المستخدم لغيره
        $this->app->singleton(LoginResponseContract::class, PortalLoginResponse::class);
        $this->app->singleton(TwoFactorLoginResponseContract::class, PortalLoginResponse::class);
    }

    public function boot(): void
    {
        // الدخول بكعكة «تذكّرني» يُعلَّم في الجلسة، فلا تبلغ به ما يشترط التحقق الثنائي (EnsureTwoFactorIsEnabled):
        // جلسة استُعيدت بالكعكة قبل ترقية الحساب إلى دور في اللوحة لا يبطلها تدوير الرمز (مراجعة المشرف على 0.14.0)
        Event::listen(Login::class, function (Login $event): void {
            if ($event->guard === 'web') {
                Auth::guard('web')->viaRemember()
                    ? session()->put(EnsureTwoFactorIsEnabled::VIA_REMEMBER, true)
                    : session()->forget(EnsureTwoFactorIsEnabled::VIA_REMEMBER);
                // ووقت الدخول، فتُنهى الجلسة إن أُنهيت جلسات الحساب بعده (sessions_revoked_at — EnsureAccountIsActive)
                session()->put(EnsureAccountIsActive::LOGGED_IN_AT, now()->getTimestamp());
            }
        });

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

        // التجزئة قبل حالة الحساب، فلا يرد الموقوف أسرع بزمن تجزئة كامل (الاختبار الأمني 0.14.0)
        // ولا دخول قبل تعيين كلمة المرور برابط البريد بعد إعادة ضبط التحقق الثنائي (must_set_password_at —
        // ResetTwoFactorByAdmin)، ولو طابقت كلمة مرور بطريق ما: مؤقتة من الإدارة مثلاً
        if (! Hash::check($password, $user->password) || ! $user->is_active || $user->must_set_password_at !== null) {
            return null;
        }

        // كلمة المرور النصية متاحة هنا وحدها: إن لم تطابق السياسة الجديدة يُطالَب صاحبها بتغييرها بعد الدخول
        // (RequirePasswordUpgrade)، فلا تُكسر الحسابات القائمة (قرار صفوان، 20 أكتوبر 2026). والمطالبة على الحساب لا في
        // الجلسة: تبقى مع «تذكّرني» وجلساته الأخرى، ولا تنتقل إلى حساب آخر يدخل في الجلسة نفسها قبل التحقق الثنائي
        $weak = ! PasswordPolicy::satisfiedBy($password, $user);

        if ($weak !== ($user->password_upgrade_required_at !== null)) {
            $user->forceFill(['password_upgrade_required_at' => $weak ? now() : null])->saveQuietly();
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
     * الدخول بالبريد أو الجوال. والقفل مشترك مع تغيير كلمة المرور والبريد والجوال (CurrentPasswordLimiter).
     */
    private function confirmPassword(User $user, ?string $password): bool
    {
        CurrentPasswordLimiter::ensureNotLocked($user, 'password');

        if ($user->is_active && $user->must_set_password_at === null && Hash::check((string) $password, $user->password)) {
            CurrentPasswordLimiter::passed($user);

            return true;
        }

        CurrentPasswordLimiter::failed($user);

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

        // رفع ملفات اللوحة المؤقت إلى القرص المحلي (Livewire): الملف يُستقبل كاملاً قبل أن يرفضه التحقق.
        // وبدء الرفع (ومنه المباشر إلى التخزين) يُحدّ في AppServiceProvider
        RateLimiter::for('uploads', fn (Request $request): array|Limit => UploadLimits::for($request, 'uploads') ?: Limit::none());

        RateLimiter::for('password-reset', fn (Request $request): Limit => Limit::perHour((int) config('security.password_reset_per_subscriber_per_hour'))
            ->by('password-reset:'.NetworkPrefix::subscriber($request->ip())));

        // تعيين كلمة المرور برمز الرابط: لكل بريد وعنوان، ولكل مشترك سقف أرحب يتسع أيام اختبار مسابقة الطلاب
        // (SchoolNetworkLimits)، فلا تحجب مدرسةٌ خلف عنوان واحد طلابَها
        RateLimiter::for('password-update', fn (Request $request): array => [
            Limit::perMinute((int) config('security.password_update_per_identity_per_minute'))
                ->by('password-update:'.mb_strtolower(trim((string) (is_string($request->input('email')) ? $request->input('email') : ''))).'|'.$request->ip()),
            Limit::perMinute(SchoolNetworkLimits::limit('password_update_per_subscriber_per_minute'))
                ->by('password-update-net:'.NetworkPrefix::subscriber($request->ip())),
        ]);

        // إشعارات تسليم واتساب: لكل مشترك (عنوان IPv4، أو /64 في IPv6). واسع لأن Meta ترسل من عناوين قليلة،
        // وحتى ثلاثة إشعارات لكل رسالة (routes/messaging.php)
        RateLimiter::for('whatsapp-webhook', fn (Request $request): Limit => Limit::perMinute((int) config('messaging.webhook_per_minute'))
            ->by('whatsapp-webhook:'.NetworkPrefix::subscriber($request->ip())));

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

        // المساعد الذكي: أسئلة كل زائر في نافذة (config/assistant.php)، وحد أوسع لشبكته (/24، أو /48 في IPv6) فلا يُلتف
        // عليه بتدوير العناوين. والعنوان في مفتاح الحد وحده لا في السجل. والتقييم أوسع قليلاً، ولا يصل إلى المزود
        RateLimiter::for('assistant', function (Request $request): array {
            $ip = NetworkPrefix::normalize($request->ip());
            $minutes = (int) config('assistant.per_visitor_minutes');
            $network = $ip === null ? 'unknown' : NetworkPrefix::prefix($ip, NetworkPrefix::isIpv6($ip) ? 48 : 24);
            $response = fn () => response()->json(['message' => __('assistant.limited')], 429);

            return [
                Limit::perMinutes($minutes, (int) config('assistant.per_visitor'))->by('assistant:'.NetworkPrefix::subscriber($ip))->response($response),
                Limit::perMinutes($minutes, (int) config('assistant.per_network'))->by('assistant-net:'.$network)->response($response),
            ];
        });

        RateLimiter::for('assistant-rating', fn (Request $request): Limit => Limit::perMinutes(10, 30)
            ->by('assistant-rating:'.NetworkPrefix::subscriber($request->ip())));

        // معرض الشواهد برابط سري: فتح الصفحة (والرمز الخاطئ معه) لكل مشترك، وملفاتها بحد أوسع لأن صور المعرض تُطلب معاً
        RateLimiter::for('evidence', fn (Request $request): Limit => Limit::perMinute((int) config('security.evidence_per_minute'))
            ->by('evidence:'.NetworkPrefix::subscriber($request->ip())));
        RateLimiter::for('evidence-files', fn (Request $request): Limit => Limit::perMinute((int) config('security.evidence_files_per_minute'))
            ->by('evidence-files:'.NetworkPrefix::subscriber($request->ip())));

        // بلاغات «قراءاتي» لكل حساب: القارئ والمشغّل يبلّغان كل عشرين ثانية على الأكثر ومع مغادرة الصفحة، فاثنا عشر في الدقيقة
        // تتسع لصفحتين مفتوحتين معاً (docs/decisions/2026-11-01-my-reading.md)
        RateLimiter::for('library-progress', fn (Request $request): Limit => Limit::perMinute(12)
            ->by('library-progress:'.($request->user()?->getAuthIdentifier() ?? NetworkPrefix::subscriber($request->ip()))));
    }
}
