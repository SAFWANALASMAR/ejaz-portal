<?php

namespace App\Http\Controllers\Competitions;

use App\Actions\Competitions\CompleteVideoUpload;
use App\Actions\Competitions\StartVideoUpload;
use App\Actions\Competitions\StoreVideoChunk;
use App\Contracts\VideoUploads;
use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competitions\StartVideoUploadRequest;
use App\Models\Competition;
use App\Models\SubmissionVideo;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * رفع مقطع المشاركة مجزأً: البدء بروابط الأجزاء، ثم الأجزاء (روابط موقّعة مؤقتة)، ثم الحالة
 * للاستئناف بروابط جديدة للناقص، ثم الإنهاء. وكل طلب لمقطع المتقدم نفسه (StoreVideoChunk وغيره).
 */
class VideoUploadController extends Controller
{
    public function start(StartVideoUploadRequest $request, string $slug, int $question, StartVideoUpload $start): JsonResponse
    {
        $user = $this->user($request);
        $competition = Competition::query()->published()->where('student_mode', false)->where('slug', $slug)->firstOrFail();
        $submission = $competition->submissions()->where('user_id', $user->id)->firstOrFail();
        $question = $competition->questions()->whereKey($question)->firstOrFail();

        $upload = $start->handle($user, $submission, $question, $request->integer('size'), $request->string('type')->toString(), $request->filled('duration') ? (float) $request->input('duration') : null);

        return response()->json([
            'video' => $upload['video']->uuid,
            'chunk_size' => SubmissionVideo::chunkSize(),
            'urls' => $upload['urls'],
        ]);
    }

    public function status(Request $request, SubmissionVideo $video, VideoUploads $uploads): JsonResponse
    {
        abort_unless($this->user($request)->can('view', $video->submission), 404);

        return response()->json([
            'status' => $video->status->value,
            'label' => $video->status->getLabel(),
            'reason' => $video->rejection_reason === null ? null : __('competitions.video.reasons.'.$video->rejection_reason),
            'chunk_size' => SubmissionVideo::chunkSize(),
            // روابط جديدة للأجزاء الناقصة فقط، فيكمل المتصفح بعد انقطاع أو انتهاء الروابط. ولا روابط بعد الإغلاق
            'urls' => $this->canUpload($request, $video) ? $uploads->urls($video, $uploads->missing($video)) : [],
        ]);
    }

    public function chunk(Request $request, SubmissionVideo $video, int $index, StoreVideoChunk $store): Response
    {
        $length = $request->header('Content-Length');

        $store->handle($this->user($request), $video, $index, $request->getContent(true), is_numeric($length) ? (int) $length : null);

        return response()->noContent();
    }

    public function complete(Request $request, SubmissionVideo $video, CompleteVideoUpload $complete): JsonResponse
    {
        $video = $complete->handle($this->user($request), $video);

        return response()->json(['status' => $video->status->value, 'label' => $video->status->getLabel()], 202);
    }

    private function canUpload(Request $request, SubmissionVideo $video): bool
    {
        return $video->status === VideoStatus::Uploading
            && $this->user($request)->can('update', $video->submission)
            && $video->submission->competition->isOpen();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
