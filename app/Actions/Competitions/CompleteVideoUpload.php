<?php

namespace App\Actions\Competitions;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Jobs\ProcessSubmissionVideo;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * إنهاء الرفع: كل الأجزاء مستلمة، فيصير المقطع قيد المعالجة، وتُجمع أجزاؤه وتُفحص في الخلفية.
 */
class CompleteVideoUpload
{
    public function __construct(private VideoUploads $uploads) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SubmissionVideo $video): SubmissionVideo
    {
        if (! $user->can('update', $video->submission)) {
            throw new NotFoundHttpException;
        }

        if ($video->status !== VideoStatus::Uploading || ! $video->submission->competition->isOpen()) {
            throw new AuthorizationException;
        }

        if ($this->uploads->missing($video) !== []) {
            throw ValidationException::withMessages(['video' => __('competitions.video.incomplete')]);
        }

        // انتقال شرطي: طلبا إنهاء متزامنان لا يرسلان المعالجة مرتين. والمشاركة مقفولة ومسودة، فلا يتداخل
        // مع إرسال متزامن
        $moved = DB::transaction(function () use ($video): int {
            CompetitionSubmission::lockDraft($video->submission_id);

            return SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Uploading)
                ->update(['status' => VideoStatus::Processing, 'updated_at' => now()]);
        });

        if ($moved === 0) {
            throw new AuthorizationException;
        }

        $video->status = VideoStatus::Processing;

        ProcessSubmissionVideo::dispatch($video->id)->afterCommit();

        return $video;
    }
}
