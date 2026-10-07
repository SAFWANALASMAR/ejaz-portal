<?php

namespace App\Support;

use App\Models\SubmissionVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use RuntimeException;

/**
 * الرفع المجزأ القابل للاستئناف لمقاطع المشاركات على القرص الخاص: روابط مؤقتة موقّعة لكل جزء،
 * والأجزاء المستلمة من الملفات نفسها (فلا سباق بين أجزاء متوازية)، ثم جمعها في ملف واحد.
 *
 * يمر الرفع هنا عبر خادم التطبيق. والرفع المباشر إلى تخزين متوافق مع S3 بروابط موقّعة قرار
 * معلّق بموافقة صفوان لأنه يحتاج مكتبة AWS (docs/decisions.md)، ويحل محل هذا الصنف حينها.
 */
final class ChunkedVideoUpload
{
    /**
     * روابط رفع الأجزاء المطلوبة، مفتاحها رقم الجزء.
     *
     * @param  list<int>  $indexes
     * @return array<int, string>
     */
    public static function urls(SubmissionVideo $video, array $indexes): array
    {
        $expires = now()->addMinutes((int) config('competitions.video.url_minutes'));

        $urls = [];

        foreach ($indexes as $index) {
            $urls[$index] = URL::temporarySignedRoute('competitions.videos.chunk', $expires, ['video' => $video->uuid, 'index' => $index]);
        }

        return $urls;
    }

    /**
     * @return list<int>
     */
    public static function received(SubmissionVideo $video): array
    {
        $indexes = array_map(fn (string $path): int => (int) basename($path), Storage::disk(SubmissionVideo::disk())->files($video->chunkDirectory()));
        sort($indexes);

        return array_values(array_filter($indexes, fn (int $index): bool => $index >= 0 && $index < $video->chunk_count));
    }

    /**
     * @return list<int>
     */
    public static function missing(SubmissionVideo $video): array
    {
        return array_values(array_diff(range(0, $video->chunk_count - 1), self::received($video)));
    }

    /**
     * ينسخ من الطلب بايتاً واحداً فوق الحجم المتوقع إلى ملف مؤقت، فإن طابق الحجم تماماً حُفظ الجزء،
     * وإلا رُفض بلا كتابة على القرص الخاص.
     *
     * @param  resource  $stream
     */
    public static function store(SubmissionVideo $video, int $index, $stream, int $expected): bool
    {
        $buffer = tmpfile();

        if ($buffer === false) {
            throw new RuntimeException('Cannot create a temporary file for the chunk.');
        }

        try {
            if (stream_copy_to_stream($stream, $buffer, $expected + 1) !== $expected) {
                return false;
            }

            rewind($buffer);
            Storage::disk(SubmissionVideo::disk())->writeStream($video->chunkDirectory().'/'.$index, $buffer);

            return true;
        } finally {
            fclose($buffer);
        }
    }

    /**
     * الأجزاء بترتيبها في ملف مؤقت محلي للفحص. والمستدعي يغلقه.
     *
     * @return resource
     */
    public static function assemble(SubmissionVideo $video)
    {
        $disk = Storage::disk(SubmissionVideo::disk());
        $target = tmpfile();

        if ($target === false) {
            throw new RuntimeException('Cannot create a temporary file for the video.');
        }

        for ($index = 0; $index < $video->chunk_count; $index++) {
            $chunk = $disk->readStream($video->chunkDirectory().'/'.$index);

            if (! is_resource($chunk)) {
                throw new RuntimeException('Missing video chunk.');
            }

            stream_copy_to_stream($chunk, $target);
            fclose($chunk);
        }

        rewind($target);

        return $target;
    }

    public static function discardChunks(SubmissionVideo $video): void
    {
        self::discardDirectory($video->uuid);
    }

    public static function discardDirectory(string $uuid): void
    {
        Storage::disk(SubmissionVideo::disk())->deleteDirectory(SubmissionVideo::CHUNK_DIRECTORY.'/'.$uuid);
    }

    /**
     * الأجزاء والملف الجامع إن وُجد.
     */
    public static function discard(SubmissionVideo $video): void
    {
        self::discardChunks($video);

        if (filled($video->path)) {
            Storage::disk(SubmissionVideo::disk())->delete((string) $video->path);
        }
    }
}
