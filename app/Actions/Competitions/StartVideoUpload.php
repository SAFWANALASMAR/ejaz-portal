<?php

namespace App\Actions\Competitions;

use App\Contracts\VideoUploads;
use App\Enums\QuestionType;
use App\Enums\VideoStatus;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionVideo;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * بدء رفع مقطع لسؤال فيديو: يفحص النوع والحجم والمدة المعلنة مقابل حدود السؤال قبل أي رفع، ثم يعيد
 * روابط الأجزاء المؤقتة. ورفع مقطع جديد للسؤال نفسه يحل محل السابق، ويُبطل روابطه (معرّف جديد).
 */
class StartVideoUpload
{
    public function __construct(private VideoUploads $uploads) {}

    /**
     * @return array{video: SubmissionVideo, urls: array<int, string>}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, CompetitionQuestion $question, int $size, string $type, ?float $duration): array
    {
        if (! $user->can('update', $submission)) {
            throw new AuthorizationException;
        }

        if (! $submission->competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        if ($question->competition_id !== $submission->competition_id || $question->type !== QuestionType::Video) {
            throw new AuthorizationException;
        }

        $maxBytes = (int) $question->max_video_mb * 1024 * 1024;

        $problem = match (true) {
            $type !== 'video/mp4' => __('competitions.video.wrong_type'),
            $size < 1 || $size > $maxBytes => __('competitions.video.too_large', ['mb' => $question->max_video_mb]),
            $duration !== null && $duration > (int) $question->max_video_seconds => __('competitions.video.too_long', ['seconds' => $question->max_video_seconds]),
            default => null,
        };

        if ($problem !== null) {
            throw ValidationException::withMessages(['video' => $problem]);
        }

        $video = DB::transaction(function () use ($submission, $question, $size): SubmissionVideo {
            // إرسال متزامن يتسلسل مع الاستبدال: فإن سبق، لا يُحذف مقطع مشاركة مرسلة ولا يُستبدل
            CompetitionSubmission::lockDraft($submission->id);

            $video = SubmissionVideo::query()->where('submission_id', $submission->id)->where('question_id', $question->id)->lockForUpdate()->first();

            if ($video !== null) {
                $this->uploads->discard($video);

                if (filled($video->path)) {
                    Storage::disk(SubmissionVideo::disk())->delete((string) $video->path);
                }
            } else {
                $video = new SubmissionVideo;
                $video->submission()->associate($submission);
                $video->question()->associate($question);
            }

            $video->uuid = (string) Str::uuid7();
            $video->status = VideoStatus::Uploading;
            $video->size = $size;
            $video->chunk_count = (int) ceil($size / SubmissionVideo::chunkSize());
            $video->duration_seconds = null;
            $video->path = null;
            $video->rejection_reason = null;
            $video->upload_id = null;
            $video->save();

            return $video;
        });

        $this->uploads->begin($video);

        return ['video' => $video, 'urls' => $this->uploads->urls($video, range(0, $video->chunk_count - 1))];
    }
}
