<?php

namespace App\Actions\Competitions;

use App\Enums\VideoStatus;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\ChunkedVideoUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * استلام جزء من المقطع: مقطع المتقدم نفسه قيد الرفع والمسابقة مفتوحة، ورقم جزء في مداه، وحجم مطابق
 * تماماً لموضعه (كامل إلا الأخير). والحجم يُفحص من الرأس قبل أي قراءة، ثم يُنسخ الجسم بسقف إلى ملف
 * مؤقت، فلا يُكتب على القرص الخاص إلا جزء صحيح. والجزء المكرر يحل محل سابقه بعد انقطاع.
 */
class StoreVideoChunk
{
    /**
     * @param  resource  $stream
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SubmissionVideo $video, int $index, $stream, ?int $declaredLength): void
    {
        if (! $user->can('update', $video->submission)) {
            throw new NotFoundHttpException;
        }

        if ($video->status !== VideoStatus::Uploading || ! $video->submission->competition->isOpen()) {
            throw new AuthorizationException;
        }

        if ($index < 0 || $index >= $video->chunk_count || $declaredLength !== $video->expectedChunkSize($index)) {
            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }

        if (! ChunkedVideoUpload::store($video, $index, $stream, $declaredLength)) {
            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }
    }
}
