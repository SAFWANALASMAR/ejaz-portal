<?php

namespace App\Http\Controllers\Competitions;

use App\Actions\Competitions\AddSubmissionWork;
use App\Actions\Competitions\RemoveSubmissionWork;
use App\Enums\WorkType;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionWork;
use App\Models\User;
use App\Support\PrivateFileResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * الأعمال المرفوعة في مشاركة المتقدم: إضافتها وحذفها مسودةً، وعرض ملفه لصاحبه وحده. والفحص في الإجراءات.
 */
class WorkController extends Controller
{
    public function store(Request $request, string $slug, AddSubmissionWork $add): RedirectResponse
    {
        $competition = Competition::query()->published()->where('slug', $slug)->firstOrFail();

        /** @var CompetitionSubmission $submission */
        $submission = $competition->submissions()->where('user_id', $this->user($request)->id)->firstOrFail();
        $submission->setRelation('competition', $competition);

        try {
            $add->handle($this->user($request), $submission, $request->only(['type', 'file', 'url']));
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.entry', $slug)->with('error', $exception->getMessage() ?: __('competitions.entry.closed'));
        }

        return redirect()->route('competitions.entry', $slug)->with('status', __('competitions.works.added'));
    }

    public function destroy(Request $request, SubmissionWork $work, RemoveSubmissionWork $remove): RedirectResponse
    {
        $this->authorizeOwner($request, $work);
        $slug = $work->submission->competition->slug;

        try {
            $remove->handle($this->user($request), $work);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.entry', $slug)->with('error', $exception->getMessage() ?: __('competitions.entry.closed'));
        }

        return redirect()->route('competitions.entry', $slug)->with('status', __('competitions.works.removed'));
    }

    public function show(Request $request, SubmissionWork $work): Response
    {
        $this->authorizeOwner($request, $work);

        abort_if(blank($work->path), 404);

        return PrivateFileResponse::make(SubmissionWork::disk(), (string) $work->path, (string) $work->mime, $work->uuid.($work->type === WorkType::Pdf ? '.pdf' : ''), (int) config('competitions.works.url_minutes'), download: $work->type === WorkType::Pdf);
    }

    /**
     * عمل مشاركة المتقدم نفسه وحده، وإلا 404 فلا يُعرف وجوده.
     */
    private function authorizeOwner(Request $request, SubmissionWork $work): void
    {
        abort_unless($work->submission?->user_id === $this->user($request)->id, 404);
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
