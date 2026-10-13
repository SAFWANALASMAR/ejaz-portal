<?php

namespace App\Jobs;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Exceptions\VideoRejected;
use App\Models\SubmissionVideo;
use App\Support\VideoProbe;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * معالجة المقطع بعد استلامه في الخلفية: جمع الأجزاء أو تنزيله من التخزين (VideoUploads)، ثم مطابقة الحجم المعلن، والنوع الحقيقي من
 * المحتوى (MP4)، والمدة الحقيقية (VideoProbe) مقابل حد السؤال. فالسليم يُحفظ باسم عشوائي في القرص الخاص
 * ويصير جاهزاً، والمخالف يُرفض بسببه ويُحذف. والحفظ شرطي على المقطع نفسه (المعرّف والحالة)، فإن
 * استُبدل أثناء المعالجة لم يُكتب فوق الجديد. وإن فشلت المعالجة نهائياً رُفض المقطع ليُعاد رفعه.
 */
class ProcessSubmissionVideo implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    // جمع مقطع كبير وكتابته مرتين يستغرق وقتاً (حتى COMPETITION_VIDEO_MAX_MB)
    public int $timeout = 900;

    public function __construct(public int $videoId)
    {
        // طابور المقاطع المستقل بمهلته (config/horizon.php — supervisor-videos)
        $this->onQueue('videos');
    }

    public function handle(VideoUploads $uploads): void
    {
        $video = SubmissionVideo::with('question')->find($this->videoId);

        if ($video === null || $video->status !== VideoStatus::Processing) {
            return;
        }

        $uuid = $video->uuid;
        $path = null;

        try {
            $file = $uploads->open($video);
        } catch (VideoRejected $rejected) {
            // المستلم لا يطابق حجمه المعلن: يُرفض دون تنزيله أو فحصه
            $file = null;
            $reason = $rejected->reason;
        }

        if ($file !== null) {
            try {
                $reason = $this->problem($video, $file);

                if ($reason === null) {
                    $path = $uploads->keep($video, $file);
                }
            } finally {
                fclose($file);
            }
        }

        $updated = SubmissionVideo::query()->whereKey($video->id)->where('uuid', $uuid)->where('status', VideoStatus::Processing)->update([
            'status' => $reason === null ? VideoStatus::Ready : VideoStatus::Rejected,
            'rejection_reason' => $reason,
            'duration_seconds' => $video->duration_seconds,
            'path' => $path,
            'updated_at' => now(),
        ]);

        if ($updated === 0 && $path !== null) {
            Storage::disk(SubmissionVideo::disk())->delete($path);
        }

        $uploads->discard($video);
    }

    /**
     * فشل نهائي (جزء مفقود، أو امتلاء القرص، أو انتهاء المهلة): يُرفض المقطع فيعيد المتقدم رفعه، ولا
     * يبقى «قيد المعالجة» يمنع الإرسال.
     */
    public function failed(?Throwable $exception): void
    {
        $video = SubmissionVideo::find($this->videoId);

        if ($video === null) {
            return;
        }

        $updated = SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Processing)
            ->update(['status' => VideoStatus::Rejected, 'rejection_reason' => 'processing_failed', 'updated_at' => now()]);

        if ($updated > 0) {
            app(VideoUploads::class)->discard($video);
        }
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

        $seconds = VideoProbe::seconds($file);

        if ($seconds === null) {
            return 'unreadable';
        }

        $video->duration_seconds = (int) ceil($seconds);

        return $seconds > (int) $video->question->max_video_seconds ? 'too_long' : null;
    }
}
