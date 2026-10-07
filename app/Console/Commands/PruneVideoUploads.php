<?php

namespace App\Console\Commands;

use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use App\Support\ChunkedVideoUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * يحذف أجزاء المقاطع المهجورة: رفع لم يكتمل منذ يوم أو أُغلقت مسابقته (فيُرفض المقطع ليُعاد رفعه)،
 * ومجلدات أجزاء بلا مقطع (حلّ محله غيره). فلا تتراكم أجزاء بلا مالك على القرص الخاص.
 */
class PruneVideoUploads extends Command
{
    protected $signature = 'ejaz:prune-video-uploads';

    protected $description = 'حذف أجزاء مقاطع المشاركات المهجورة';

    public function handle(): int
    {
        $abandoned = 0;

        SubmissionVideo::query()
            ->where('status', VideoStatus::Uploading)
            ->where(fn ($query) => $query
                ->where('updated_at', '<', now()->subDay())
                ->orWhereHas('submission.competition', fn ($query) => $query->where('closes_at', '<', now())))
            ->lazyById()
            ->each(function (SubmissionVideo $video) use (&$abandoned): void {
                $updated = SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Uploading)
                    ->update(['status' => VideoStatus::Rejected, 'rejection_reason' => 'abandoned', 'updated_at' => now()]);

                if ($updated > 0) {
                    ChunkedVideoUpload::discardDirectory($video->uuid);
                    $abandoned++;
                }
            });

        $orphans = 0;
        $disk = Storage::disk(SubmissionVideo::disk());

        foreach ($disk->directories(SubmissionVideo::CHUNK_DIRECTORY) as $directory) {
            $uuid = basename($directory);

            if (! Str::isUuid($uuid) || ! SubmissionVideo::query()->where('uuid', $uuid)->whereIn('status', [VideoStatus::Uploading, VideoStatus::Processing])->exists()) {
                $disk->deleteDirectory($directory);
                $orphans++;
            }
        }

        $this->info("مقاطع مهجورة: {$abandoned}، ومجلدات بلا مقطع: {$orphans}.");

        return self::SUCCESS;
    }
}
