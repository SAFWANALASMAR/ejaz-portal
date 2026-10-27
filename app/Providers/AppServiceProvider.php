<?php

namespace App\Providers;

use App\Actions\Content\RecordMenuReorder;
use App\Auth\VerifiedFirstUserProvider;
use App\Contracts\AiAssistant;
use App\Contracts\BotChallenge;
use App\Contracts\OnlineMeetings;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Contracts\StoreCatalog;
use App\Contracts\VideoUploads;
use App\Contracts\WhatsAppNotifier;
use App\Enums\OnlineMeetingFailure;
use App\Enums\RoleName;
use App\Exceptions\OnlineMeetingFailed;
use App\Listeners\SendVerificationWhenRequired;
use App\Models\AssistantSettings;
use App\Models\FormEntry;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ExportPolicy;
use App\Services\Assistant\AssistantProviders;
use App\Services\BotChallenge\FakeBotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
use App\Services\OnlineMeetings\FakeOnlineMeetings;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\MoyasarPaymentGateway;
use App\Services\Sms\ConfiguredSmsGateway;
use App\Services\Store\SettingsStoreCatalog;
use App\Services\VideoUploads\S3VideoUploads;
use App\Services\WhatsApp\ConfiguredWhatsAppNotifier;
use App\Services\WhatsApp\FakeWhatsAppNotifier;
use App\Support\AssemblyArea;
use App\Support\Assistant\AssistantIndex;
use App\Support\ChunkedVideoUpload;
use App\Support\PasswordPolicy;
use App\Support\ReadOnlyAccess;
use App\Support\SpaLink;
use App\Support\TeamsConnection;
use App\Support\UploadLimits;
use App\View\Composers\SiteChromeComposer;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Auth\Listeners\SendEmailVerificationNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\Mechanisms\FrontendAssets\FrontendAssets;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;

use function Livewire\on;

class AppServiceProvider extends ServiceProvider
{
    /**
     * سمات سكربت Livewire في صفحات التنقّل السريع (SpaLink::currentPage).
     */
    private const NAVIGATE_SCRIPT_ATTRIBUTES = ['defer' => true, 'data-navigate-track' => 'reload'];

    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(RecordMenuReorder::class);

        // رسالة التوثيق بعد التسجيل تتبع مفتاحه في اللوحة (ملاحظات المدير التنفيذي، 25 أكتوبر 2026)
        $this->app->bind(SendEmailVerificationNotification::class, SendVerificationWhenRequired::class);

        // التكاملات الخارجية خلف واجهات، ويُختار التطبيق من الإعدادات (docs/architecture.md)
        // الرسائل النصية وواتساب: المزود يُختار عند كل إرسال من «قنوات التواصل» في اللوحة، وإلا من .env
        // (docs/guides/messaging.md). والوهمي لواتساب في الاختبارات وحده
        $this->app->singleton(SmsGateway::class, fn () => new ConfiguredSmsGateway);
        $this->app->singleton(WhatsAppNotifier::class, fn () => config('services.whatsapp.driver') === 'fake'
            ? new FakeWhatsAppNotifier
            : new ConfiguredWhatsAppNotifier);

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

        // خطأ الإعداد يُرمى عند أول استعمال، لا رفضاً صامتاً لكل زائر. ولا يُحل إلا لغير المسجل: في استبيان يقبل
        // الضيوف وفي التبرع (صفحتاهما وإرسالهما)، فلا يمس أصحاب الحسابات. والفحص عند كل نشر في docs/security.md
        $this->app->singleton(BotChallenge::class, function () {
            $site = (string) config('services.bot_challenge.turnstile_site_key');
            $secret = (string) config('services.bot_challenge.turnstile_secret_key');

            return match (config('services.bot_challenge.driver')) {
                'fake' => $this->app->isProduction()
                    ? throw new RuntimeException('The fake bot challenge must not be used in production; set BOT_CHALLENGE_DRIVER=turnstile.')
                    : new FakeBotChallenge,
                'turnstile' => $site === '' || $secret === ''
                    ? throw new RuntimeException('Turnstile needs TURNSTILE_SITE_KEY and TURNSTILE_SECRET_KEY.')
                    : new TurnstileChallenge($site, $secret, parse_url((string) config('app.url'), PHP_URL_HOST) ?: null),
                default => throw new InvalidArgumentException('Unsupported bot challenge driver: '.config('services.bot_challenge.driver')),
            };
        });

        // اجتماعات تيمز للجمعية العمومية: يُحل عند المزامنة وحدها، وخطأ إعداده يظهر للسكرتير عند حفظ اجتماع عن
        // بعد (SaveMeeting)، ولا يمس غيره
        // لا singleton: الإعداد من اللوحة يُقرأ عند كل استعمال، فلا يبقى عامل الطابور على إعداد قديم.
        // والربط المطفأ أو الناقص OnlineMeetingFailed بسببه، فيُعرض على الاجتماع للمسؤول
        $this->app->bind(OnlineMeetings::class, fn () => match (config('services.online_meetings.driver')) {
            'fake' => $this->app->isProduction()
                ? throw new OnlineMeetingFailed(OnlineMeetingFailure::NotConfigured)
                : new FakeOnlineMeetings,
            'graph' => new GraphOnlineMeetings(TeamsConnection::current() ?? throw new OnlineMeetingFailed(OnlineMeetingFailure::NotConfigured)),
            default => throw new InvalidArgumentException('Unsupported online meetings driver: '.config('services.online_meetings.driver')),
        });

        // المساعد الذكي: المزود من إعدادات اللوحة عند كل استعمال (requirements/10)، فلا يُحل إلا حين يُرسل إليه
        $this->app->bind(AiAssistant::class, fn () => AssistantProviders::make(AssistantSettings::current()));

        $this->app->singleton(VideoUploads::class, function () {
            $disk = SubmissionVideo::disk();

            return match (config('competitions.video.upload')) {
                'app' => new ChunkedVideoUpload,
                's3' => new S3VideoUploads(self::s3Disk($disk)->getClient(), (string) config("filesystems.disks.{$disk}.bucket"), (string) config("filesystems.disks.{$disk}.root", '')),
                default => throw new InvalidArgumentException('Unsupported video upload driver: '.config('competitions.video.upload')),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // يكشف التحميل الكسول (N+1) والحقول غير المعرّفة أثناء التطوير
        Model::shouldBeStrict(! $this->app->isProduction());

        // البريد الموثق أولاً لوسيط الاستعادة (VerifiedFirstUserProvider)
        Auth::provider('verified-first', fn ($app, array $config): VerifiedFirstUserProvider => new VerifiedFirstUserProvider($app['hash'], $config['model']));

        $this->guardPaymentDriver();

        // قواعد كلمة المرور في docs/security.md، وتعريفها في PasswordPolicy (قرار صفوان، 20 أكتوبر 2026)
        Password::defaults(fn () => PasswordPolicy::defaults());

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

        // كل رفع ملف في اللوحة يبدأ بـ _startUpload، حتى المباشر إلى التخزين (s3) الذي لا يمر بمسار الرفع
        // المحدود، فيُحدّ هنا لكل حساب (الفحص الأمني 12 أكتوبر)
        on('call', function (Component $component, string $method): void {
            if ($method === '_startUpload') {
                UploadLimits::hitStart(request());
            }
        });

        // مديرو النظام (المدير التنفيذي والمسؤول التقني) يملكون كل ما لم تمنعه سياسة صراحة، والمشرف العام الاطلاع منه وحده
        // (ReadOnlyAccess — المدير التنفيذي عبر صفوان، 25 أكتوبر 2026). Gate::after لا يغيّر نتيجة صريحة (false أو
        // Response::deny من سياسة)، فتسري قواعد مثل: لا تعديل لسجل العمليات، ولا حذف للنفس
        Gate::after(function (User $user, string $ability, mixed $result): ?bool {
            if ($result !== null) {
                return null;
            }

            return RoleName::isAdministrator($user)
                || ($user->hasRole(RoleName::SuperAdmin->value) && ReadOnlyAccess::allows($ability))
                ? true
                : null;
        });

        // نموذج سجل العمليات من مكتبة خارجية، فتُسجَّل سياسته صراحة
        Gate::policy(Activity::class, ActivityPolicy::class);

        // ملفات تصدير Filament (الطلبات، والتسجيل في البرامج، والمشاركات): لمن طلبها وما زال يملك صلاحية
        // تصديرها، بتحقق ثنائي
        Gate::policy(Export::class, ExportPolicy::class);

        // منطقة الجمعية العمومية في الموقع: لعضوها والمجلس والسكرتير والمشرف العام (AssemblyArea). وفيها المشرف
        // العام صراحة، فالنتيجة الصريحة لا يغيّرها Gate::after
        Gate::define('accessAssemblyArea', fn (User $user): bool => AssemblyArea::allows($user));

        // «نماذجي» في لوحة المستخدم لمن في ملفه نموذج مشاركين معتمد (FormEntry::scopeVisibleToPerson)
        Gate::define('viewOwnFormEntries', fn (User $user): bool => FormEntry::query()->visibleToPerson($user)->exists());

        View::composer(['partials.header', 'partials.footer'], SiteChromeComposer::class);

        // فهرس المساعد يتبع المحتوى: كل حفظ وحذف واستعادة يعيد مقاطع السجل، فلا تبقى مسودة ولا محذوف فيه
        foreach (array_keys(AssistantIndex::MODELS) as $model) {
            $model::saved(fn (Model $record) => AssistantIndex::sync($record));
            $model::deleted(fn (Model $record) => AssistantIndex::sync($record));

            if (in_array(SoftDeletes::class, class_uses_recursive($model), true)) {
                $model::restored(fn (Model $record) => AssistantIndex::sync($record));
            }
        }

        // @navigate($url): wire:navigate على رابط صفحة داخلية، ولا شيء لما يُحمَّل تحميلاً كاملاً (SpaLink)
        Blade::directive('navigate', fn (string $url): string => "<?php if (\\App\\Support\\SpaLink::allows({$url})) echo 'wire:navigate'; ?>");

        // سكربت Livewire في الواجهة العامة (components/layouts/app.blade.php) مؤجل فلا يوقف عرض الصفحة، وتغيّر إصداره
        // (?id=) بعد نشر يعيد تحميلها عند التنقّل التالي. لمسارات الواجهة وحدها، وتُضبط سماته مع كل طلب: Livewire
        // يحفظها في كائن واحد يبقى بين طلبات العامل نفسه (الاختبارات، وOctane)، فلا يرثها سكربت لوحة الإدارة
        Event::listen(RouteMatched::class, function (RouteMatched $event): void {
            $assets = app(FrontendAssets::class);
            $assets->scriptTagAttributes = array_diff_key($assets->scriptTagAttributes, self::NAVIGATE_SCRIPT_ATTRIBUTES);

            if (SpaLink::currentPage($event->request)) {
                Livewire::useScriptTagAttributes(self::NAVIGATE_SCRIPT_ATTRIBUTES);
            }
        });
    }

    /**
     * الرفع المباشر يكتب في القرص الخاص نفسه، فيلزم أن يكون قرص S3.
     */
    private static function s3Disk(string $disk): AwsS3V3Adapter
    {
        $adapter = Storage::disk($disk);

        if (! $adapter instanceof AwsS3V3Adapter) {
            throw new InvalidArgumentException("The private disk [{$disk}] must use the s3 driver for direct video uploads.");
        }

        return $adapter;
    }

    /**
     * الدفع في الإنتاج متى فُعّل: قائمة سماح لا قائمة منع، فيُرفض عند الإقلاع لا عند أول تبرع كل مشغّل غير moyasar
     * (fake أو قيمة خاطئة)، وكل مفتاح سري فارغ. والدفع مؤرشف خلف PAYMENTS_ENABLED=false (مساراته 404، وصفحة الدفع
     * الوهمية للمحلي والاختبار وحدهما)، فلا يُفحص حينها، وإلا تعطّل بناء صورة الإنتاج (package:discover بلا .env)
     * والموقع كله بقالب .env.production.example.
     */
    protected function guardPaymentDriver(): void
    {
        if (! $this->app->isProduction() || ! config('payments.enabled')) {
            return;
        }

        if (config('payments.driver') !== 'moyasar') {
            throw new RuntimeException('PAYMENT_DRIVER must be moyasar in production while payments are enabled.');
        }

        foreach (['payments.moyasar.secret_key' => 'MOYASAR_SECRET_KEY', 'payments.webhook_secret' => 'PAYMENT_WEBHOOK_SECRET'] as $key => $variable) {
            if (trim((string) config($key)) === '') {
                throw new RuntimeException($variable.' must be set in production while payments are enabled.');
            }
        }
    }
}
