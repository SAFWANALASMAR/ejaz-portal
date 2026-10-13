<?php

namespace App\Support;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * الرفع المجزأ القابل للاستئناف لمقاطع المشاركات على القرص الخاص: روابط مؤقتة موقّعة لكل جزء،
 * والأجزاء المستلمة من الملفات نفسها (فلا سباق بين أجزاء متوازية)، ثم جمعها في ملف واحد.
 *
 * يمر الرفع هنا عبر خادم التطبيق (COMPETITION_VIDEO_UPLOAD=app)، وهو للتطوير وللاستضافة بلا
 * تخزين سحابي. والرفع المباشر إلى التخزين السحابي في S3VideoUploads.
 */
final class ChunkedVideoUpload implements VideoUploads
{
    public function begin(SubmissionVideo $video): void {}

    /**
     * روابط رفع الأجزاء المطلوبة، مفتاحها رقم الجزء.
     *
     * @param  list<int>  $indexes
     * @return array<int, string>
     */
    public function urls(SubmissionVideo $video, array $indexes): array
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
    public function received(SubmissionVideo $video): array
    {
        $indexes = array_map(fn (string $path): int => (int) basename($path), Storage::disk(SubmissionVideo::disk())->files($video->chunkDirectory()));
        sort($indexes);

        return array_values(array_filter($indexes, fn (int $index): bool => $index >= 0 && $index < $video->chunk_count));
    }

    /**
     * @return list<int>
     */
    public function missing(SubmissionVideo $video): array
    {
        return array_values(array_diff(range(0, $video->chunk_count - 1), $this->received($video)));
    }

    /**
     * ينسخ من الطلب بايتاً واحداً فوق الحجم المتوقع إلى ملف مؤقت، فإن طابق الحجم تماماً حُفظ الجزء،
     * وإلا رُفض بلا كتابة على القرص الخاص.
     *
     * @param  resource  $stream
     */
    public function store(SubmissionVideo $video, int $index, $stream, int $expected): bool
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
    public function open(SubmissionVideo $video)
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

    public function keep(SubmissionVideo $video, $file): string
    {
        rewind($file);
        $path = SubmissionVideo::DIRECTORY.'/'.Str::random(40).'.mp4';
        Storage::disk(SubmissionVideo::disk())->writeStream($path, $file);

        return $path;
    }

    public function discard(SubmissionVideo $video): void
    {
        Storage::disk(SubmissionVideo::disk())->deleteDirectory($video->chunkDirectory());
    }

    /**
     * مجلدات أجزاء بلا مقطع يُرفع أو يُعالج (حلّ محله غيره).
     */
    public function pruneOrphans(): int
    {
        $orphans = 0;
        $disk = Storage::disk(SubmissionVideo::disk());

        foreach ($disk->directories(SubmissionVideo::CHUNK_DIRECTORY) as $directory) {
            $uuid = basename($directory);

            if (! Str::isUuid($uuid) || ! SubmissionVideo::query()->where('uuid', $uuid)->whereIn('status', [VideoStatus::Uploading, VideoStatus::Processing])->exists()) {
                $disk->deleteDirectory($directory);
                $orphans++;
            }
        }

        return $orphans;
    }
}
