<?php

namespace App\Models;

use App\Models\Concerns\FlushesContentCache;
use App\Models\Concerns\Publishable;
use App\Models\Contracts\HasPublicImages;
use App\Support\ContentBlocks;
use Database\Factories\PageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * صفحة محتوى بمقاطع مرنة يحررها المحرر من اللوحة. و«عن الجمعية» (about) و«سياسة الخصوصية» (privacy)
 * لا تُحذفان ولا يتغير معرّفاهما (LOCKED_SLUGS)، لأن رابطيهما /about و/privacy مبنيان عليهما.
 *
 * وصفحات «المقدمة» (INTROS) نصوص صفحات الموقع السابق التي صار لها قالب مبني في البوابة: تُعرض
 * مقاطعها أعلى صفحة القالب (x-page-intro)، ورابطها /pages/{slug} يحوّل إليه.
 */
#[Fillable(['slug', 'title', 'summary', 'blocks', 'is_published', 'is_sample'])]
class Page extends Model implements HasPublicImages
{
    /** @use HasFactory<PageFactory> */
    use FlushesContentCache, HasFactory, LogsActivity, Publishable;

    public const ABOUT = 'about';

    public const PRIVACY = 'privacy';

    /**
     * صفحات لها رابط خاص مبني على معرّفها، ويشير إليها التسجيل والقائمة: لا تُحذف ولا يتغير معرّفها.
     */
    public const LOCKED_SLUGS = [self::ABOUT, self::PRIVACY];

    public const PUBLISHED_CACHE_KEY = 'pages.published';

    public const IMAGE_DIRECTORY = 'pages';

    /**
     * معرّف صفحة المقدمة ← اسم مسار القالب الذي تُعرض فيه ومعاملاته.
     *
     * @var array<string, array{0: string, 1: array<string, string>}>
     */
    public const INTROS = [
        'contact' => ['contact', []],
        'inquiries' => ['contact', []],
        'assembly-membership' => ['services.assembly-membership', []],
        'jobs' => ['services.jobs', []],
        'volunteering' => ['services.volunteering', []],
        'reports' => ['governance.index', ['category' => 'annual-reports']],
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'blocks' => 'array',
            'is_published' => 'boolean',
            'is_sample' => 'boolean',
        ];
    }

    /**
     * رابط القالب الذي تُعرض فيه صفحة المقدمة، أو null إن لم تكن منها.
     */
    public static function introUrl(string $slug, bool $absolute = true): ?string
    {
        if (! isset(self::INTROS[$slug])) {
            return null;
        }

        [$route, $parameters] = self::INTROS[$slug];

        return route($route, $parameters, $absolute);
    }

    protected static function booted(): void
    {
        static::saved(fn () => self::flushPublishedSlugs());
        static::deleted(fn () => self::flushPublishedSlugs());
    }

    /**
     * معرّفات الصفحات المنشورة. تقرؤها القائمة والتذييل في كل صفحة، فمفتاحها مستقل عن ذاكرة المحتوى:
     * لا يُعاد استعلامها مع كل تعديل في محتوى آخر. ويمسحها حفظ صفحة أو حذفها، وأمر ejaz:refresh-content
     * بعد النشر والاستعادة والتعديل المباشر في القاعدة، وساعة احتياطية.
     *
     * @return list<string>
     */
    public static function publishedSlugs(): array
    {
        return Cache::remember(self::PUBLISHED_CACHE_KEY, now()->addHour(), fn (): array => self::query()->published()->pluck('slug')->all());
    }

    /**
     * بعد اعتماد المعاملة، وإلا قرأ طلب متزامن القائمة القديمة وخزّنها (كالقائمة في MenuItem).
     */
    public static function flushPublishedSlugs(): void
    {
        DB::afterCommit(fn () => Cache::forget(self::PUBLISHED_CACHE_KEY));
    }

    public static function isPublishedSlug(string $slug): bool
    {
        return in_array($slug, self::publishedSlugs(), true);
    }

    /**
     * معرّف الصفحة التي يعرضها رابط داخلي (/about و/privacy و/pages/{slug})، أو null لغيرها. والرابط الكامل
     * على مضيف البوابة نفسه داخلي أيضاً (كـ SpaLink)، بـ www أو بدونها. والمقارنة بلا حالة الأحرف، فلا يفلت /About.
     * وصفحات المقدمة null، لأن رابطها يحوّل إلى قالبها منشورةً أو لا.
     */
    public static function slugForUrl(string $url): ?string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return null;
        }

        $host = fn (string $host): string => (string) preg_replace('/^www\./', '', strtolower($host));

        if (isset($parts['host'])) {
            if ($host($parts['host']) !== $host((string) parse_url((string) config('app.url'), PHP_URL_HOST))) {
                return null;
            }
        } elseif (! str_starts_with($url, '/')) {
            return null;
        }

        $path = strtolower(trim($parts['path'] ?? '', '/'));

        $slug = match (true) {
            $path === self::ABOUT, $path === self::PRIVACY => $path,
            preg_match('#^pages/([a-z0-9]+(?:-[a-z0-9]+)*)$#', $path, $matches) === 1 => $matches[1],
            default => null,
        };

        return $slug !== null && ! isset(self::INTROS[$slug]) ? $slug : null;
    }

    public function imageDirectory(): string
    {
        return self::IMAGE_DIRECTORY;
    }

    /**
     * @return array<string, list<string>>
     */
    public function publicImages(): array
    {
        return ['blocks' => ContentBlocks::images($this->blocks)];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
