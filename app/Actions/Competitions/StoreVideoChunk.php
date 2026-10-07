<?php

namespace App\Actions\Competitions;

use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\ChunkedVideoUpload;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * استلام جزء من المقطع: مقطع المتقدم نفسه قيد الرفع والمسابقة مفتوحة، ورقم جزء في مداه، وحجم مطابق
 * تماماً لموضعه (كامل إلا الأخير). والحجم يُفحص من الرأس قبل أي قراءة، ثم يُنسخ الجسم بسقف إلى ملف
 * مؤقت، فلا يُكتب على القرص الخاص إلا جزء صحيح. والجزء المكرر يحل محل سابقه بعد انقطاع.
 */
class StoreVideoChunk
{
    public function __construct(private VideoUploads $uploads) {}

    /**
     * @param  resource  $stream
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, SubmissionVideo $video, int $index, $stream, ?int $declaredLength): void
    {
        // الأجزاء تمر بالخادم في الرفع عبره وحده، وفي الرفع المباشر تذهب إلى التخزين
        if (! $this->uploads instanceof ChunkedVideoUpload || ! $user->can('update', $video->submission)) {
            throw new NotFoundHttpException;
        }

        if ($video->status !== VideoStatus::Uploading || ! $video->submission->competition->isOpen()) {
            throw new AuthorizationException;
        }

        if ($index < 0 || $index >= $video->chunk_count || $declaredLength !== $video->expectedChunkSize($index)) {
            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }

        // الفحص على الصفين المقفولين: المشاركة مسودة، والمقطع نفسه قيد الرفع. والكتابة بعد المعاملة،
        // فلا يُمسك القفل طوال قراءة الجزء من الشبكة. ومقطع قيد الرفع يمنع الإرسال (SubmitSubmission)
        DB::transaction(function () use ($video): void {
            CompetitionSubmission::lockDraft($video->submission_id);

            if (! SubmissionVideo::query()->whereKey($video->id)->where('uuid', $video->uuid)->where('status', VideoStatus::Uploading)->exists()) {
                throw new AuthorizationException;
            }
        });

        if (! $this->uploads->store($video, $index, $stream, $declaredLength)) {
            throw ValidationException::withMessages(['index' => __('competitions.video.bad_chunk')]);
        }

        // رفع نشط ولو استؤنف بعد مدة، فلا يعدّه التنظيف مهجوراً (ejaz:prune-video-uploads)
        SubmissionVideo::query()->whereKey($video->id)->update(['updated_at' => now()]);
    }
}
