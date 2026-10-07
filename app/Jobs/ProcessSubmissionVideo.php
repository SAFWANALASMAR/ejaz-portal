<?php

namespace App\Jobs;

use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use App\Support\ChunkedVideoUpload;
use App\Support\Mp4Duration;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * معالجة المقطع بعد استلامه في الخلفية: جمع الأجزاء، ثم مطابقة الحجم المعلن، والنوع الحقيقي من
 * المحتوى (MP4)، والمدة من ترويسته مقابل حد السؤال. فالسليم يُحفظ باسم عشوائي في القرص الخاص
 * ويصير جاهزاً، والمخالف يُرفض بسببه ويُحذف.
 */
class ProcessSubmissionVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $videoId) {}

    public function handle(): void
    {
        $video = SubmissionVideo::with('question')->find($this->videoId);

        if ($video === null || $video->status !== VideoStatus::Processing) {
            return;
        }

        $file = ChunkedVideoUpload::assemble($video);

        try {
            $reason = $this->problem($video, $file);

            if ($reason === null) {
                rewind($file);
                $path = SubmissionVideo::DIRECTORY.'/'.Str::random(40).'.mp4';
                Storage::disk(SubmissionVideo::disk())->writeStream($path, $file);
                $video->path = $path;
                $video->status = VideoStatus::Ready;
            } else {
                $video->status = VideoStatus::Rejected;
                $video->rejection_reason = $reason;
            }
        } finally {
            fclose($file);
        }

        // مقطع حلّ محله غيره أثناء المعالجة لا يُكتب فوق الجديد
        $current = SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Processing)->exists();

        if (! $current) {
            if (filled($video->path)) {
                Storage::disk(SubmissionVideo::disk())->delete((string) $video->path);
            }

            return;
        }

        $video->save();
        ChunkedVideoUpload::discardChunks($video);
    }

    /**
     * @param  resource  $file
     */
    private function problem(SubmissionVideo $video, $file): ?string
    {
        $stat = fstat($file);

        if (! is_array($stat) || (int) $stat['size'] !== $video->size) {
            return 'size_mismatch';
        }

        $type = (string) (new \finfo(FILEINFO_MIME_TYPE))->buffer((string) fread($file, 4096));

        if ($type !== 'video/mp4') {
            return 'not_mp4';
        }

        $seconds = Mp4Duration::seconds($file);

        if ($seconds === null) {
            return 'unreadable';
        }

        $video->duration_seconds = (int) ceil($seconds);

        return $seconds > (int) $video->question->max_video_seconds ? 'too_long' : null;
    }
}
