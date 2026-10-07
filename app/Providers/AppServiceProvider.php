<?php

namespace App\Providers;

use App\Actions\Content\RecordMenuReorder;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Contracts\StoreCatalog;
use App\Enums\RoleName;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ExportPolicy;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\MoyasarPaymentGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Store\SettingsStoreCatalog;
use App\View\Composers\SiteChromeComposer;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RecordMenuReorder::class);

        // التكاملات الخارجية خلف واجهات، ويُختار التطبيق من الإعدادات (docs/architecture.md)
        $this->app->singleton(SmsGateway::class, fn () => match (config('services.sms.driver')) {
            'log' => new LogSmsGateway,
            default => throw new InvalidArgumentException('Unsupported SMS driver: '.config('services.sms.driver')),
        });

        // بوابة الدفع: وهمية في التطوير والاختبارات، وميسر بمفاتيح من .env (docs/plans/store-payments.md)
        $this->app->bind(MoyasarPaymentGateway::class, fn () => new MoyasarPaymentGateway(
            (string) config('payments.moyasar.secret_key'),
            (string) config('payments.webhook_secret'),
        ));
        $this->app->singleton(PaymentGateway::class, fn () => match (config('payments.driver')) {
            'fake' => new FakePaymentGateway,
            'moyasar' => $this->app->make(MoyasarPaymentGateway::class),
            default => throw new InvalidArgumentException('Unsupported payment driver: '.config('payments.driver')),
        });

        // منتجات المتجر: من الإعدادات حتى تتوفر واجهة سلة البرمجية
        $this->app->singleton(StoreCatalog::class, fn () => match (config('store.driver')) {
            'settings' => new SettingsStoreCatalog,
            default => throw new InvalidArgumentException('Unsupported store driver: '.config('store.driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // يكشف التحميل الكسول (N+1) والحقول غير المعرّفة أثناء التطوير
        Model::shouldBeStrict(! $this->app->isProduction());

        // المشغّل الوهمي للدفع يُرفض عند الإقلاع في الإنتاج، لا عند أول تبرع
        if ($this->app->isProduction() && config('payments.driver') === 'fake') {
            throw new RuntimeException('PAYMENT_DRIVER=fake must not be used in production; set moyasar.');
        }

        // قواعد كلمة المرور في docs/security.md
        Password::defaults(fn () => Password::min(10)->uncompromised());

        // الروابط المطلقة (asset وroute وurl) من APP_URL لا من رأس Host في الطلب، فلا يُسمَّم
        // ما يُخزَّن من HTML (ContentCache) بنطاق آخر. ويرفض trustHosts المضيف المجهول في الإنتاج
        URL::forceRootUrl((string) config('app.url'));

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

        // المشرف العام يملك كل ما لم تمنعه سياسة صراحة. Gate::after لا يغيّر نتيجة صريحة
        // (false أو Response::deny من سياسة)، فتسري قواعد مثل: لا تعديل لسجل العمليات، ولا حذف للنفس
        Gate::after(fn (User $user, string $ability, mixed $result): ?bool => $result === null && $user->hasRole(RoleName::SuperAdmin->value) ? true : null);

        // نموذج سجل العمليات من مكتبة خارجية، فتُسجَّل سياسته صراحة
        Gate::policy(Activity::class, ActivityPolicy::class);

        // ملفات تصدير Filament (المسابقات): لمن طلبها وما زال يملك صلاحية التصدير
        Gate::policy(Export::class, ExportPolicy::class);

        View::composer(['partials.header', 'partials.footer'], SiteChromeComposer::class);
    }
}
