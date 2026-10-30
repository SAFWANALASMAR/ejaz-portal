<?php

namespace App\Jobs;

use App\Actions\Settings\UpdateSiteSettings;
use App\Models\Setting;
use App\Rules\VideoUrl;
use App\Support\ContentCache;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;

/**
 * صورة مقطع الرئيسية المفرد (رابط يوتيوب في «إعدادات الموقع») تُجلب مرة من الخادم بآلية صور المكتبة نفسها
 * (FetchYoutubeThumbnail)، فلا يتصل متصفح الزائر بجوجل قبل ضغطه (ملاحظة المدير التنفيذي، 29 أكتوبر 2026). واسمها من معرّف
 * المقطع، فتُعرف بلا حقل في الإعدادات يمحوه حفظ النموذج، ويتركها تنظيف القرص العام ما دام المقطع نفسه (PrunePublicFiles).
 */
class FetchHomeVideoThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public readonly string $youtube) {}

    public static function path(string $youtube): string
    {
        if (preg_match(VideoUrl::YOUTUBE_ID, $youtube) !== 1) {
            throw new InvalidArgumentException('Invalid YouTube id.');
        }

        return UpdateSiteSettings::IMAGE_DIRECTORY.'/'.FetchYoutubeThumbnail::PREFIX.$youtube.'.jpg';
    }

    /**
     * رابط الصورة إن جُلبت، وإلا null فيبقى المقطع بخلفيته الداكنة.
     */
    public static function url(string $youtube): ?string
    {
        $disk = Storage::disk('public');

        return $disk->exists(self::path($youtube)) ? $disk->url(self::path($youtube)) : null;
    }

    /**
     * معرّف مقطع الرئيسية في الإعدادات، أو null.
     */
    public static function currentId(): ?string
    {
        $url = Setting::group('home')['video_url'] ?? null;

        return VideoUrl::youtubeId(is_string($url) ? $url : null);
    }

    /**
     * يطلب صورة المقطع الحالي إن لم تُجلب: بعد حفظ الإعدادات (UpdateSiteSettings)، وبعد كل نشر (ejaz:refresh-content).
     */
    public static function queueForCurrent(): void
    {
        $youtube = self::currentId();

        if ($youtube !== null && ! Storage::disk('public')->exists(self::path($youtube))) {
            self::dispatch($youtube);
        }
    }

    public function handle(): void
    {
        if (preg_match(VideoUrl::YOUTUBE_ID, $this->youtube) !== 1) {
            return;
        }

        if (Storage::disk('public')->exists(self::path($this->youtube))) {
            return;
        }

        try {
            $jpeg = FetchYoutubeThumbnail::download($this->youtube);
        } catch (ConnectionException) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        if ($jpeg === null) {
            return;
        }

        Storage::disk('public')->put(self::path($this->youtube), $jpeg);
        // الرئيسية مخزّنة، فتظهر الصورة فوراً بمسح مفتاحها وحده (HomeController)
        ContentCache::forget('home');
    }
}
