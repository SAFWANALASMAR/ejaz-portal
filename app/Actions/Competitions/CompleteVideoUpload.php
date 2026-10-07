<?php

namespace App\Actions\Competitions;

use App\Enums\VideoStatus;
use App\Jobs\ProcessSubmissionVideo;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\ChunkedVideoUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * إنهاء الرفع: كل الأجزاء مستلمة، فيصير المقطع قيد المعالجة، وتُجمع أجزاؤه وتُفحص في الخلفية.
 */
class CompleteVideoUpload
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SubmissionVideo $video): SubmissionVideo
    {
        if (! $user->can('update', $video->submission) || $video->status !== VideoStatus::Uploading) {
            throw new AuthorizationException;
        }

        if (ChunkedVideoUpload::missing($video) !== []) {
            throw ValidationException::withMessages(['video' => __('competitions.video.incomplete')]);
        }

        $video->status = VideoStatus::Processing;
        $video->save();

        ProcessSubmissionVideo::dispatch($video->id)->afterCommit();

        return $video;
    }
}
