<?php

namespace App\Http\Controllers\Competitions;

use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\SubmissionReview;
use App\Models\SubmissionVideo;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/**
 * مقطع المشاركة لمن يطّلع على تحكيمها (المحكّم المسند إليه، أو المتابع بصلاحية submissions.view)،
 * يُفحص عند كل طلب. وعلى القرص المحلي بطلب الجزء (Range) للتقديم والتأخير، وفي الإنتاج يُسلَّم للخادم
 * أو لرابط تخزين موقّع قصير بعد الفحص (roadmap.md).
 */
class ReviewVideoController extends Controller
{
    public function __invoke(Request $request, SubmissionReview $review, SubmissionVideo $video): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('view', $review) && $video->submission_id === $review->submission_id && $video->status === VideoStatus::Ready, 404);

        $disk = Storage::disk(SubmissionVideo::disk());
        $headers = ['Content-Type' => 'video/mp4', 'Cache-Control' => 'private, no-store'];

        if (config('filesystems.disks.'.SubmissionVideo::disk().'.driver') === 'local') {
            return response()->file($disk->path((string) $video->path), $headers);
        }

        return $disk->response((string) $video->path, $video->uuid.'.mp4', $headers, 'inline');
    }
}
