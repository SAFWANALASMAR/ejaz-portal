<?php

namespace App\Http\Controllers\Competitions;

use App\Enums\VideoStatus;
use App\Http\Controllers\Controller;
use App\Models\SubmissionReview;
use App\Models\SubmissionVideo;
use App\Models\User;
use App\Support\PrivateFileResponse;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * مقطع المشاركة لمن يطّلع على تحكيمها (المحكّم المسند إليه، أو المتابع بصلاحية submissions.view)،
 * يُفحص عند كل طلب. وعلى القرص المحلي بطلب الجزء (Range) للتقديم والتأخير، وعلى التخزين السحابي
 * برابط موقّع قصير بعد الفحص (PrivateFileResponse)، فلا يمر المقطع عبر PHP.
 */
class ReviewVideoController extends Controller
{
    public function __invoke(Request $request, SubmissionReview $review, SubmissionVideo $video): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('view', $review) && $video->submission_id === $review->submission_id && $video->status === VideoStatus::Ready, 404);

        // المسار خارج وسطاء اللوحة، فيُشترط التحقق الثنائي هنا كما في ExportPolicy (قرار صفوان)
        abort_unless($user->hasEnabledTwoFactorAuthentication() && $user->canAccessPanel(Filament::getPanel('admin')), 403);

        // ورابط قصير (5 دقائق)، والمشغّل يعيد طلب هذا المسار عند الخطأ أو التوقف (filament/reviews/video — البلاغ 185، I2)
        return PrivateFileResponse::make(SubmissionVideo::disk(), (string) $video->path, 'video/mp4', $video->uuid.'.mp4', (int) config('competitions.video.review_url_minutes'));
    }
}
