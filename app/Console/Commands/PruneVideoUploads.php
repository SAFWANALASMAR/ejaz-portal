<?php

namespace App\Console\Commands;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use Illuminate\Console\Command;

/**
 * يحذف أجزاء المقاطع المهجورة: رفع لم يكتمل منذ يوم أو أُغلقت مسابقته (فيُرفض المقطع ليُعاد رفعه)،
 * ومجلدات أجزاء بلا مقطع (حلّ محله غيره). فلا تتراكم أجزاء بلا مالك على القرص الخاص.
 */
class PruneVideoUploads extends Command
{
    protected $signature = 'ejaz:prune-video-uploads';

    protected $description = 'حذف أجزاء مقاطع المشاركات المهجورة';

    public function handle(VideoUploads $uploads): int
    {
        $abandoned = 0;

        SubmissionVideo::query()
            ->where('status', VideoStatus::Uploading)
            ->where(fn ($query) => $query
                ->where('updated_at', '<', now()->subDay())
                ->orWhereHas('submission.competition', fn ($query) => $query->where('closes_at', '<', now())))
            ->lazyById()
            ->each(function (SubmissionVideo $video) use (&$abandoned, $uploads): void {
                $updated = SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Uploading)
                    ->update(['status' => VideoStatus::Rejected, 'rejection_reason' => 'abandoned', 'updated_at' => now()]);

                if ($updated > 0) {
                    $uploads->discard($video);
                    $abandoned++;
                }
            });

        $orphans = $uploads->pruneOrphans();

        $this->info("مقاطع مهجورة: {$abandoned}، ومجلدات بلا مقطع: {$orphans}.");

        return self::SUCCESS;
    }
}
