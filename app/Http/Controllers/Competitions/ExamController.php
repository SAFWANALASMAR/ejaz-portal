<?php

namespace App\Http\Controllers\Competitions;

use App\Actions\Competitions\AcceptExamInstructions;
use App\Actions\Competitions\FinishExamIntro;
use App\Actions\Competitions\RegisterForExam;
use App\Actions\Competitions\SaveExamAnswer;
use App\Actions\Competitions\StartExamIntro;
use App\Actions\Competitions\SubmitExam;
use App\Enums\ExamStage;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\CompetitionMedia;
use App\Support\ExamQuestions;
use App\Support\PrivateFileResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * رحلة الاختبار الإلكتروني (docs/plans/competition-exam.md): التسجيل، ثم الفيديو التعريفي، ثم التعليمات،
 * ثم الأسئلة سؤالاً سؤالاً، ثم المراجعة والإرسال، ثم رسالة الختام. والصفحة الرئيسة تعرض المرحلة الحالية،
 * وكل طلب لمرحلة لم يحن وقتها يُرفض في الإجراءات (ExamLock) ويعود إليها. ولا يصل الخيار الصحيح إلى أي صفحة.
 */
class ExamController extends Controller
{
    public function show(Request $request, string $slug): View|RedirectResponse
    {
        [$competition, $submission] = $this->context($request, $slug);

        if ($submission === null) {
            return redirect()->route('competitions.show', $competition->slug);
        }

        $stage = $submission->examStage();

        if ($stage !== ExamStage::Submitted && ! $competition->isOpen()) {
            return view('competitions.exam.closed', compact('competition', 'submission'));
        }

        return match ($stage) {
            ExamStage::Registration => view('competitions.exam.register', [
                'competition' => $competition,
                'submission' => $submission,
                'phone' => $this->user($request)->phone,
                'email' => $this->user($request)->email,
            ]),
            ExamStage::Intro => view('competitions.exam.intro', compact('competition', 'submission')),
            ExamStage::Instructions => view('competitions.exam.instructions', compact('competition', 'submission')),
            ExamStage::Questions => $this->resume($submission),
            ExamStage::Submitted => view('competitions.exam.finished', compact('competition', 'submission')),
        };
    }

    /**
     * العائد إلى الاختبار: أول سؤال لم يُجب، أو المراجعة إن أجاب الكل.
     */
    private function resume(CompetitionSubmission $submission): RedirectResponse
    {
        $slug = $submission->competition->slug;
        $position = $this->questions($submission)->search(fn (CompetitionQuestion $question): bool => $submission->answer($question) === null);

        return $position === false
            ? redirect()->route('competitions.exam.review', $slug)
            : redirect()->route('competitions.exam.question', [$slug, $position + 1]);
    }

    public function register(Request $request, string $slug, RegisterForExam $register): RedirectResponse
    {
        return $this->step($request, $slug, fn (User $user, CompetitionSubmission $submission) => $register->handle($user, $submission, $request->only(['full_name', 'national_id', 'contact_email', 'education_stage', 'pledge'])));
    }

    public function introStart(Request $request, string $slug, StartExamIntro $start): JsonResponse
    {
        [, $submission] = $this->required($request, $slug);
        $start->handle($this->user($request), $submission);

        return response()->json(['started' => true]);
    }

    public function introFinish(Request $request, string $slug, FinishExamIntro $finish): RedirectResponse
    {
        return $this->step($request, $slug, fn (User $user, CompetitionSubmission $submission) => $finish->handle($user, $submission));
    }

    public function introVideo(Request $request, string $slug): Response
    {
        [$competition, $submission] = $this->required($request, $slug);

        abort_unless($submission->examStage() === ExamStage::Intro && $competition->isOpen() && filled($competition->intro_video_path), 404);

        // الرابط يبقى ضعف مدة المقطع وزيادة، فلا ينقطع التشغيل بتوقف أو تقطيع قبل حدث النهاية
        $minutes = max((int) config('competitions.exam.media_url_minutes'), (int) ceil((int) $competition->intro_video_seconds / 60) * 2 + 5);

        return PrivateFileResponse::make(CompetitionMedia::disk(), (string) $competition->intro_video_path, 'video/mp4', 'intro.mp4', $minutes);
    }

    public function instructions(Request $request, string $slug, AcceptExamInstructions $accept): RedirectResponse
    {
        return $this->step($request, $slug, fn (User $user, CompetitionSubmission $submission) => $accept->handle($user, $submission, $request->only('accept')));
    }

    public function question(Request $request, string $slug, int $number): View|RedirectResponse
    {
        [$competition, $submission] = $this->required($request, $slug);

        if ($submission->examStage() !== ExamStage::Questions || ! $competition->isOpen()) {
            return redirect()->route('competitions.exam', $slug);
        }

        $questions = $this->questions($submission);
        $question = $questions->get($number - 1);

        abort_if($question === null, 404);

        return view('competitions.exam.question', [
            'competition' => $competition,
            'submission' => $submission,
            'questions' => $questions,
            'question' => $question,
            'number' => $number,
            'total' => $questions->count(),
            'options' => $submission->orderedOptions($question),
        ]);
    }

    public function questionMedia(Request $request, string $slug, int $number): Response
    {
        [$competition, $submission] = $this->required($request, $slug);

        abort_unless($submission->examStage() === ExamStage::Questions && $competition->isOpen(), 404);

        $question = $this->questions($submission)->get($number - 1);

        abort_if($question === null || $question->media_type === null || blank($question->media_path), 404);

        $mime = (string) (CompetitionMedia::mime((string) $question->media_path) ?? 'application/octet-stream');

        abort_unless(in_array($mime, $question->media_type->mimeTypes(), true), 404);

        return PrivateFileResponse::make(CompetitionMedia::disk(), (string) $question->media_path, $mime, 'media-'.$number, (int) config('competitions.exam.media_url_minutes'));
    }

    public function answer(Request $request, string $slug, SaveExamAnswer $save): RedirectResponse
    {
        $request->validate([
            'question' => ['required', 'integer'],
            'option' => ['nullable', 'string', 'max:10'],
            'go' => ['required', 'string', 'max:10'],
        ]);

        [$competition, $submission] = $this->required($request, $slug);

        try {
            $save->handle($this->user($request), $submission, $request->integer('question'), $request->filled('option') ? $request->string('option')->toString() : null);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.exam', $slug)->with('error', $exception->getMessage() ?: __('competitions.exam.wrong_stage'));
        }

        $questions = $this->questions($submission);
        $current = (int) $questions->search(fn (CompetitionQuestion $question): bool => $question->id === $request->integer('question')) + 1;
        $go = (string) $request->input('go');

        $target = match (true) {
            $go === 'prev' => max(1, $current - 1),
            $go === 'next' => $current + 1,
            ctype_digit($go) => (int) $go,
            default => null,
        };

        if ($target === null || $target > $questions->count() || $target < 1) {
            return redirect()->route('competitions.exam.review', $slug);
        }

        return redirect()->route('competitions.exam.question', [$competition->slug, $target]);
    }

    public function review(Request $request, string $slug): View|RedirectResponse
    {
        [$competition, $submission] = $this->required($request, $slug);

        if ($submission->examStage() !== ExamStage::Questions || ! $competition->isOpen()) {
            return redirect()->route('competitions.exam', $slug);
        }

        return view('competitions.exam.review', [
            'competition' => $competition,
            'submission' => $submission,
            'questions' => $this->questions($submission),
        ]);
    }

    public function submit(Request $request, string $slug, SubmitExam $submit): RedirectResponse
    {
        [, $submission] = $this->required($request, $slug);

        try {
            $submit->handle($this->user($request), $submission);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.exam', $slug)->with('error', $exception->getMessage() ?: __('competitions.exam.wrong_stage'));
        }

        return redirect()->route('competitions.exam', $slug);
    }

    /**
     * خطوة من خطوات المراحل: الرفض لمرحلة لم يحن وقتها يعود إلى المرحلة الحالية برسالته.
     *
     * @param  callable(User, CompetitionSubmission): mixed  $action
     */
    private function step(Request $request, string $slug, callable $action): RedirectResponse
    {
        [, $submission] = $this->required($request, $slug);

        try {
            $action($this->user($request), $submission);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.exam', $slug)->with('error', $exception->getMessage() ?: __('competitions.exam.wrong_stage'));
        }

        return redirect()->route('competitions.exam', $slug);
    }

    /**
     * أسئلة الاختبار بترتيب المتقدم، من الذاكرة المؤقتة بلا الخيار الصحيح.
     *
     * @return Collection<int, CompetitionQuestion>
     */
    private function questions(CompetitionSubmission $submission): Collection
    {
        ExamQuestions::load($submission->competition);

        return $submission->orderedQuestions();
    }

    /**
     * المسابقة المنشورة بنوع الاختبار، ومشاركة المتقدم نفسه فيها أو null. فلا يصل أحد إلى مشاركة غيره.
     *
     * @return array{0: Competition, 1: CompetitionSubmission|null}
     */
    private function context(Request $request, string $slug): array
    {
        $competition = Competition::query()->published()->where('exam_mode', true)->where('slug', $slug)->firstOrFail();

        /** @var CompetitionSubmission|null $submission */
        $submission = $competition->submissions()->where('user_id', $this->user($request)->id)->first();
        $submission?->setRelation('competition', $competition);

        return [$competition, $submission];
    }

    /**
     * @return array{0: Competition, 1: CompetitionSubmission}
     */
    private function required(Request $request, string $slug): array
    {
        [$competition, $submission] = $this->context($request, $slug);

        abort_if($submission === null, 404);

        return [$competition, $submission];
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
