<?php

namespace App\Models;

use App\Enums\LiveStreamProvider;
use App\Enums\LiveStreamStatus;
use App\Rules\SafeUrl;
use App\Rules\VideoUrl;
use Database\Factories\LiveStreamFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * بث مباشر: يوتيوب أو تيمز، بموعد بدء وانتهاء، تُحسب حالته منهما ولا تُخزَّن، فتظهر صفحته `/live` وتختفي
 * آلياً. وبعد انتهائه يُنقل تسجيله إلى المكتبة المرئية (ArchiveLiveStream) فيُربط بعنصرها. يُحفظ عبر
 * SaveLiveStream.
 *
 * @property LiveStreamProvider $provider
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $archived_at
 */
#[Fillable(['title', 'description', 'provider', 'url', 'starts_at', 'ends_at', 'is_published'])]
class LiveStream extends Model
{
    /** @use HasFactory<LiveStreamFactory> */
    use HasFactory, LogsActivity, SoftDeletes;

    /**
     * شارة الرأس تُقرأ من ذاكرة قصيرة تُمسح عند أي حفظ.
     */
    public const ANNOUNCED_CACHE_KEY = 'live-stream.announced';

    private const TEAMS_HOSTS = ['teams.microsoft.com', 'teams.live.com'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'provider' => LiveStreamProvider::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'archived_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        $flush = fn () => DB::afterCommit(fn () => Cache::forget(self::ANNOUNCED_CACHE_KEY));

        static::saved($flush);
        static::deleted($flush);
    }

    /**
     * @return BelongsTo<LibraryItem, $this>
     */
    public function libraryItem(): BelongsTo
    {
        return $this->belongsTo(LibraryItem::class)->withTrashed();
    }

    /**
     * @param  Builder<LiveStream>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }

    /**
     * الجاري ثم أقرب القادم: منشور، لم يُنقل، ولم ينتهِ.
     *
     * @param  Builder<LiveStream>  $query
     */
    public function scopeCurrent(Builder $query, ?Carbon $now = null): void
    {
        $query->published()->whereNull('archived_at')->where('ends_at', '>', $now ?? now())->orderBy('starts_at')->orderBy('id');
    }

    public function status(?Carbon $now = null): LiveStreamStatus
    {
        $now ??= now();

        return match (true) {
            $this->archived_at !== null => LiveStreamStatus::Archived,
            ! $this->is_published => LiveStreamStatus::Draft,
            $now->lt($this->starts_at) => LiveStreamStatus::Upcoming,
            $now->lt($this->ends_at) => LiveStreamStatus::Live,
            default => LiveStreamStatus::Ended,
        };
    }

    public function youtubeId(): ?string
    {
        return $this->provider === LiveStreamProvider::Youtube ? VideoUrl::youtubeId($this->url) : null;
    }

    /**
     * رابط الانضمام إلى اجتماع تيمز إن كان آمناً ومن نطاق تيمز، وإلا null فلا يُعرض.
     */
    public function joinUrl(): ?string
    {
        return $this->provider === LiveStreamProvider::Teams && self::isTeamsUrl($this->url) ? $this->url : null;
    }

    public static function isTeamsUrl(?string $url): bool
    {
        if (! SafeUrl::isSafe($url, allowInternal: false)) {
            return false;
        }

        $host = strtolower((string) parse_url((string) $url, PHP_URL_HOST));

        return in_array($host, self::TEAMS_HOSTS, true) || str_ends_with($host, '.teams.microsoft.com');
    }

    /**
     * ما يُعلن في رأس الصفحات: بث جارٍ أو قادم خلال أيام الإعلان (config/livestream.php)، وإلا null.
     *
     * @return array{title: string, live: bool}|null
     */
    public static function announced(): ?array
    {
        /** @var array{title: string, live: bool}|array{} $announced */
        $announced = Cache::remember(self::ANNOUNCED_CACHE_KEY, 60, function (): array {
            $stream = static::query()->current()->where('starts_at', '<=', now()->addDays((int) config('livestream.announce_days')))->first();

            return $stream === null ? [] : ['title' => $stream->title, 'live' => $stream->status() === LiveStreamStatus::Live];
        });

        return $announced === [] ? null : $announced;
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('content')->logFillable()->logOnlyDirty()->dontLogEmptyChanges();
    }
}
