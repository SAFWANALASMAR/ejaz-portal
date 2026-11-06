<?php

namespace App\Http\Controllers\Competitions;

use App\Actions\Competitions\SaveSubmissionDraft;
use App\Actions\Competitions\StartSubmission;
use App\Actions\Competitions\SubmitSubmission;
use App\Http\Controllers\Controller;
use App\Http\Requests\Competitions\SaveSubmissionRequest;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * نموذج المشاركة: تُنشأ المسودة بزر «شارك الآن» (POST بحد معدل)، فلا ينشئ فتح الرابط شيئاً.
 * ثم تُحفظ يدوياً أو تلقائياً، ثم تُرسل. وبعد الإرسال يعرض ملخصها ورقمها المرجعي. والفحص كله في الإجراءات.
 */
class EntryController extends Controller
{
    public function start(Request $request, string $slug, StartSubmission $start): RedirectResponse
    {
        $competition = $this->competition($slug);
        $user = $this->user($request);

        // بريد موثق أو جوال موثق (decisions/2026-11-06-email-otp.md)
        if (! $user->hasVerifiedEmail() && ! $user->hasVerifiedPhone()) {
            return $user->email !== null
                ? redirect()->route('verification.notice')->with('status', __('account.email_code.required'))
                : redirect()->route('account.phone.verify')->with('status', __('competitions.entry.verify_phone'));
        }

        try {
            $start->handle($user, $competition);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.show', $competition->slug)->with('error', $exception->getMessage());
        }

        return redirect()->route('competitions.entry', $competition->slug);
    }

    public function show(Request $request, string $slug): View|RedirectResponse
    {
        $competition = $this->competition($slug);

        /** @var CompetitionSubmission|null $submission */
        $submission = $competition->submissions()->where('user_id', $this->user($request)->id)->first();

        // بلا مسودة: صفحة المسابقة وزرها، فلا تُنشأ مسودة من GET
        if ($submission === null) {
            return redirect()->route('competitions.show', $competition->slug);
        }

        // الاختبار الإلكتروني برحلته المتتابعة (ExamController)
        if ($competition->exam_mode) {
            return redirect()->route('competitions.exam', $competition->slug);
        }

        $submission->load(['competition.questions', 'videos', 'works']);

        return view('competitions.entry', [
            'competition' => $competition,
            'submission' => $submission,
            'editable' => $submission->isDraft() && $competition->isOpen(),
        ]);
    }

    public function save(SaveSubmissionRequest $request, string $slug, SaveSubmissionDraft $save): RedirectResponse
    {
        $save->handle($this->user($request), $this->submission($request, $slug), $request->entry());

        return redirect()->route('competitions.entry', $slug)->with('status', __('competitions.entry.saved'));
    }

    public function autosave(SaveSubmissionRequest $request, string $slug, SaveSubmissionDraft $save): JsonResponse
    {
        $save->handle($this->user($request), $this->submission($request, $slug), $request->entry());

        return response()->json(['saved' => true]);
    }

    public function submit(SaveSubmissionRequest $request, string $slug, SubmitSubmission $submit): RedirectResponse
    {
        $submission = $submit->handle($this->user($request), $this->submission($request, $slug), $request->entry());

        return redirect()->route('competitions.entry', $slug)->with('status', __('competitions.entry.submitted', ['reference' => $submission->reference]));
    }

    private function competition(string $slug): Competition
    {
        return Competition::query()->published()->where('student_mode', false)->where('slug', $slug)->firstOrFail();
    }

    /**
     * مشاركة المتقدم نفسه في المسابقة، أو 404. فلا يصل أحد إلى مشاركة غيره من الرابط.
     */
    private function submission(Request $request, string $slug): CompetitionSubmission
    {
        return $this->competition($slug)->submissions()->where('user_id', $this->user($request)->id)->firstOrFail();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
