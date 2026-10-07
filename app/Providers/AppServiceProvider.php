<?php

namespace App\Providers;

use App\Actions\Content\RecordMenuReorder;
use App\Contracts\BotChallenge;
use App\Contracts\SmsGateway;
use App\Contracts\VideoUploads;
use App\Enums\RoleName;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Policies\ExportPolicy;
use App\Services\BotChallenge\FakeBotChallenge;
use App\Services\BotChallenge\TurnstileChallenge;
use App\Services\Sms\LogSmsGateway;
use App\Services\VideoUploads\S3VideoUploads;
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

        $this->app->singleton(BotChallenge::class, fn () => match (config('services.bot_challenge.driver')) {
            'fake' => new FakeBotChallenge,
            'turnstile' => new TurnstileChallenge((string) config('services.bot_challenge.turnstile_site_key'), (string) config('services.bot_challenge.turnstile_secret_key')),
            default => throw new InvalidArgumentException('Unsupported bot challenge driver: '.config('services.bot_challenge.driver')),
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
