<?php

namespace App\Http\Controllers\Competitions;

use App\Http\Controllers\Controller;
use App\Models\SubmissionReview;
use App\Models\SubmissionWork;
use App\Models\User;
use App\Support\PrivateFileResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ملف عمل المشاركة لمن يطّلع على تحكيمها (المحكّم المسند إليه، أو المتابع بصلاحية submissions.view)،
 * يُفحص عند كل طلب، وبالتحقق الثنائي كمقطع التحكيم (ReviewVideoController).
 */
class ReviewWorkController extends Controller
{
    public function __invoke(Request $request, SubmissionReview $review, SubmissionWork $work): Response
    {
        /** @var User $user */
        $user = $request->user();

        abort_unless($user->can('view', $review) && $work->submission_id === $review->submission_id && filled($work->path), 404);
        abort_unless($user->hasEnabledTwoFactorAuthentication(), 403);

        return PrivateFileResponse::make(SubmissionWork::disk(), (string) $work->path, (string) $work->mime, $work->uuid, (int) config('competitions.works.url_minutes'));
    }
}
