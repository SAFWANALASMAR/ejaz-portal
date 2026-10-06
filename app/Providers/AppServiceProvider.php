<?php

namespace App\Providers;

use App\Contracts\SmsGateway;
use App\Enums\RoleName;
use App\Models\User;
use App\Services\Sms\LogSmsGateway;
use App\View\Composers\SiteChromeComposer;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // التكاملات الخارجية خلف واجهات، ويُختار التطبيق من الإعدادات (docs/architecture.md)
        $this->app->singleton(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unsupported SMS driver: '.config('services.sms.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // يكشف التحميل الكسول (N+1) والحقول غير المعرّفة أثناء التطوير
        Model::shouldBeStrict(! $this->app->isProduction());

        // قواعد كلمة المرور في docs/security.md
        Password::defaults(fn () => Password::min(10)->uncompromised());

        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }

        // خلف موزع الأحمال: عنوان الزائر الحقيقي من رؤوس X-Forwarded-*.
        // يُضبط هنا لا في bootstrap/app.php لأن الإعدادات لا تكون محمّلة هناك بعد.
        $proxies = config('security.trusted_proxies');

        if (is_string($proxies) && $proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_map('trim', explode(',', $proxies)));
        }

        // التخزين بتوقيت UTC والعرض بتوقيت الرياض
        FilamentTimezone::set(config('app.display_timezone'));

        // المشرف العام يملك كل الصلاحيات
        Gate::before(fn (User $user): ?bool => $user->hasRole(RoleName::SuperAdmin->value) ? true : null);

        View::composer(['partials.header', 'partials.footer'], SiteChromeComposer::class);
    }
}
