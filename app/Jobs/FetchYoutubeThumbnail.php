<?php

namespace App\Jobs;

use App\Models\LibraryItem;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * الصورة المصغرة لمقطع يوتيوب في المكتبة تُجلب مرة من الخادم وتُحفظ على القرص العام، فلا يتصل متصفح الزائر بخوادم جوجل
 * عند عرض القائمة (قرار 7 أكتوبر 2026، ومراجعة المشرف المبكرة لملاحظات المدير التنفيذي، ١٤). ولعنصر بلا صورة مرفوعة وحده.
 *
 * تُعاد ترميزاً بـ GD، فلا يُحفظ إلا صورة JPEG بلا بيانات وصفية. وما تعذّر جلبه يبقى بلا صورة، ويعرض العنصر علامة التشغيل.
 */
class FetchYoutubeThumbnail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public const MAX_BYTES = 2 * 1024 * 1024;

    /**
     * بادئة اسم الصورة المجلوبة قبل معرّف المقطع، فتُعرف من المرفوعة وتُترك إن تغيّر الرابط (SaveLibraryItem).
     */
    public const PREFIX = 'yt-';

    public function __construct(public readonly int $itemId) {}

    public static function isFetched(string $path): bool
    {
        return str_starts_with($path, LibraryItem::IMAGE_DIRECTORY.'/'.self::PREFIX);
    }

    public static function isFetchedFor(string $path, ?string $youtubeId): bool
    {
        return $youtubeId !== null && str_starts_with($path, LibraryItem::IMAGE_DIRECTORY.'/'.self::PREFIX.$youtubeId.'-');
    }

    public function handle(): void
    {
        $item = LibraryItem::query()->find($this->itemId);
        $youtube = $item?->youtubeId();

        if ($item === null || $youtube === null || filled($item->thumbnail)) {
            return;
        }

        try {
            $response = Http::timeout(10)->withoutRedirecting()->get('https://i.ytimg.com/vi/'.$youtube.'/hqdefault.jpg');
        } catch (ConnectionException) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 300);

            return;
        }

        $bytes = $response->successful() ? $response->body() : '';
        $image = $bytes !== '' && strlen($bytes) <= self::MAX_BYTES ? @imagecreatefromstring($bytes) : false;

        if ($image === false) {
            return;
        }

        ob_start();
        imagejpeg($image, null, 85);
        $jpeg = (string) ob_get_clean();

        $path = LibraryItem::IMAGE_DIRECTORY.'/'.self::PREFIX.$youtube.'-'.Str::uuid().'.jpg';
        Storage::disk('public')->put($path, $jpeg);

        // الرابط ربما تغيّر، أو رفع المحرر صورة، أثناء الجلب
        $fresh = $item->fresh();

        if ($fresh === null || $fresh->youtubeId() !== $youtube || filled($fresh->thumbnail)) {
            Storage::disk('public')->delete($path);

            return;
        }

        // حفظ عادي، فتُمسح ذاكرة المحتوى (FlushesContentCache) وتظهر الصورة فوراً
        $fresh->forceFill(['thumbnail' => $path])->save();
    }
}
