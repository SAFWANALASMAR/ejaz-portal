<?php

namespace App\Providers;

use App\Actions\Content\RecordMenuReorder;
use App\Contracts\BotChallenge;
use App\Contracts\OnlineMeetings;
use App\Contracts\PaymentGateway;
use App\Contracts\SmsGateway;
use App\Contracts\StoreCatalog;
use App\Contracts\VideoUploads;
use App\Contracts\WhatsAppNotifier;
use App\Enums\RoleName;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ExportPolicy;
use App\Services\BotChallenge\FakeBotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
use App\Services\OnlineMeetings\FakeOnlineMeetings;
use App\Services\OnlineMeetings\GraphOnlineMeetings;
use App\Services\Payments\FakePaymentGateway;
use App\Services\Payments\MoyasarPaymentGateway;
use App\Services\Sms\LogSmsGateway;
use App\Services\Store\SettingsStoreCatalog;
use App\Services\VideoUploads\S3VideoUploads;
use App\Services\WhatsApp\FakeWhatsAppNotifier;
use App\Services\WhatsApp\LogWhatsAppNotifier;
use App\Support\AssemblyArea;
use App\Support\ChunkedVideoUpload;
use App\View\Composers\SiteChromeComposer;
use Filament\Actions\Exports\Models\Export;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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

        // واتساب: ينتظر قرار صفوان في المزوّد، فالسجل للتطوير والوهمي للاختبارات (docs/plans/competition-exam.md)
        $this->app->singleton(WhatsAppNotifier::class, fn () => match (config('services.whatsapp.driver')) {
            'log' => new LogWhatsAppNotifier,
            'fake' => new FakeWhatsAppNotifier,
            default => throw new InvalidArgumentException('Unsupported WhatsApp driver: '.config('services.whatsapp.driver')),
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
        $this->app->singleton(OnlineMeetings::class, function () {
            /** @var array<string, mixed> $graph */
            $graph = (array) config('services.online_meetings.graph');
            $settings = array_map(fn (mixed $value): string => (string) $value, $graph);

            return match (config('services.online_meetings.driver')) {
                'fake' => $this->app->isProduction()
                    ? throw new RuntimeException('The fake online meetings must not be used in production; set ONLINE_MEETINGS_DRIVER=graph.')
                    : new FakeOnlineMeetings,
                'graph' => in_array('', [$settings['tenant_id'] ?? '', $settings['client_id'] ?? '', $settings['client_secret'] ?? '', $settings['organizer'] ?? ''], true)
                    ? throw new RuntimeException('Microsoft Graph needs MS_GRAPH_TENANT_ID, MS_GRAPH_CLIENT_ID, MS_GRAPH_CLIENT_SECRET and MS_GRAPH_ORGANIZER.')
                    : new GraphOnlineMeetings($settings['tenant_id'], $settings['client_id'], $settings['client_secret'], $settings['organizer']),
                default => throw new InvalidArgumentException('Unsupported online meetings driver: '.config('services.online_meetings.driver')),
            };
        });

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

        // ملفات تصدير Filament (الطلبات، والتسجيل في البرامج، والمشاركات): لمن طلبها وما زال يملك صلاحية
        // تصديرها، بتحقق ثنائي
        Gate::policy(Export::class, ExportPolicy::class);

        // منطقة الجمعية العمومية في الموقع: لعضوها والمجلس والسكرتير والمشرف العام (AssemblyArea). وفيها المشرف
        // العام صراحة، فالنتيجة الصريحة لا يغيّرها Gate::after
        Gate::define('accessAssemblyArea', fn (User $user): bool => AssemblyArea::allows($user));

        View::composer(['partials.header', 'partials.footer'], SiteChromeComposer::class);
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
}
