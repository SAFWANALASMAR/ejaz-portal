<?php

namespace App\Http\Controllers;

use App\Actions\Activity\LogAccess;
use App\Enums\ActivityEvent;
use App\Http\Requests\Achievements\AchievementsRequest;
use App\Models\Achievement;
use App\Support\PrivateFileResponse;
use App\Support\StoragePath;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * «إنجازاتنا» العامة: الخط الزمني للمنشور وحده، ومرفق الإنجاز برابط مؤقت موقّع (المسار signed) بعد فحص
 * AchievementPolicy::viewAttachment عند الطلب: المنشور لكل من معه الرابط، والمسودة لمن يطّلع عليها في اللوحة، ويُسجَّل
 * اطلاعه. والصورة تُعرض، والـ PDF يُنزَّل (PrivateFileResponse)، فلا رابط تخزين عام لأي مرفق.
 */
class AchievementController extends Controller
{
    public const PER_PAGE = 12;

    public function index(AchievementsRequest $request): View
    {
        $achievements = Achievement::query()->timeline()->paginate(self::PER_PAGE)->withQueryString();

        abort_if($achievements->currentPage() > max(1, $achievements->lastPage()), 404);

        return view('achievements.index', ['achievements' => $achievements]);
    }

    public function attachment(Request $request, Achievement $achievement, LogAccess $log): Response
    {
        abort_unless(Gate::forUser($request->user())->allows('viewAttachment', $achievement), 403);

        $extension = $achievement->attachmentExtension();
        abort_if($achievement->attachment === null || $extension === null || ! StoragePath::inDirectory($achievement->attachment, Achievement::DIRECTORY), 404);

        // اطلاع إداري على مرفق مسودة يُسجَّل، ومرفق المنشور محتوى عام
        if (! $achievement->isPublished() && $request->user() !== null) {
            $log->handle($request->user(), $achievement, 'content', ActivityEvent::FileDownloaded, ['file' => $achievement->attachment_name]);
        }

        return PrivateFileResponse::make(
            Achievement::disk(),
            $achievement->attachment,
            Achievement::MIME_TYPES[$extension],
            (string) $achievement->attachment_name,
            5,
            download: ! $achievement->attachmentIsImage(),
        );
    }
}
