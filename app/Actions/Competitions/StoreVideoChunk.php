<?php

namespace App\Actions\Competitions;

use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\ChunkedVideoUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

/**
 * استلام جزء من المقطع: مقطع المتقدم نفسه قيد الرفع، ورقم جزء في مداه، وحجم مطابق تماماً لموضعه
 * (كامل إلا الأخير). والجزء المكرر يحل محل سابقه، فيصح إعادة إرساله بعد انقطاع.
 */
class StoreVideoChunk
{
    /**
     * @param  resource  $stream
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SubmissionVideo $video, int $index, $stream): void
    {
        if (! $user->can('update', $video->submission) || $video->status !== VideoStatus::Uploading) {
            throw new AuthorizationException;
        }

        if ($index < 0 || $index >= $video->chunk_count) {
            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }

        ChunkedVideoUpload::store($video, $index, $stream);

        if (ChunkedVideoUpload::chunkSize($video, $index) !== $video->expectedChunkSize($index)) {
            Storage::disk(SubmissionVideo::disk())->delete($video->chunkDirectory().'/'.$index);

            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }
    }
}
