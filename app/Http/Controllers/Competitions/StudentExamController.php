<?php

namespace App\Http\Controllers\Competitions;

use App\Actions\Competitions\RegisterStudent;
use App\Actions\Competitions\SaveAttemptAnswers;
use App\Actions\Competitions\StartAttempt;
use App\Actions\Competitions\SubmitAttempt;
use App\Contracts\BotChallenge;
use App\Http\Controllers\Controller;
use App\Models\Competition;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRegion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\RoundQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * مسابقة الطلاب (docs/plans/student-competition.md): التسجيل، ثم الاختبار في نافذة كل مرحلة وحدها: صفحة
 * البدء، فوحدة في كل صفحة (نص أو مقطع بأسئلته، أو سؤال منفرد) بعداد ظاهر، فالمراجعة والتسليم، فـ«تم استلام
 * إجاباتك». والفحص كله في الإجراءات، ولا تصل إجابة صحيحة ولا درجة إلى أي صفحة.
 */
class StudentExamController extends Controller
{
    public function registerForm(Request $request, string $slug): View|RedirectResponse
    {
        $competition = $this->competition($slug);

        if ($this->registration($request, $competition) !== null) {
            return redirect()->route('competitions.show', $slug);
        }

        abort_unless($competition->isOpen(), 404);

        return view('competitions.student.register', [
            'competition' => $competition,
            'categories' => $competition->openCategories()->get(),
            'regions' => CompetitionRegion::query()->active()->get(),
            'fields' => $competition->fields()->get(),
        ]);
    }

    public function register(Request $request, string $slug, RegisterStudent $register): RedirectResponse
    {
        $competition = $this->competition($slug);

        // التحدي ضد الآليات (Turnstile) على نموذج التسجيل في المسابقة أيضاً، ولو كان الطالب داخلاً (مراجعة المطابقة).
        // ووسيط VerifyBotChallenge لنماذج الزائر وحده، فيُفحص هنا بفعل النموذج نفسه
        $token = $request->input('cf-turnstile-response');

        if (! app(BotChallenge::class)->passes(is_string($token) ? $token : '', 'competition-register')) {
            return back()->withInput($request->except(['national_id', 'guardian_phone', 'cf-turnstile-response']))
                ->withErrors(['bot' => __('account.bot_challenge.failed')]);
        }

        try {
            $register->handle($this->user($request), $competition, $request->only(['full_name', 'national_id', 'category_id', 'grade', 'region_id', 'guardian_phone', 'fields', 'pledge']));
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.show', $slug)->with('error', $exception->getMessage() ?: __('competitions.student.registration_closed'));
        }

        return redirect()->route('competitions.show', $slug)->with('status', __('competitions.student.registered'));
    }

    /**
     * صفحة المرحلة: البدء قبل المحاولة، أو العودة إلى أول وحدة لم تكتمل، أو «تم استلام إجاباتك».
     */
    public function show(Request $request, string $slug, string $round): View|RedirectResponse
    {
        [$competition, $stage, $submission] = $this->context($request, $slug, $round);
        $attempt = $this->attempt($request, $stage, $submission);

        if ($attempt === null) {
            return view('competitions.student.start', [
                'competition' => $competition,
                'round' => $stage,
                'eligible' => ! $stage->qualified_only || app(StartAttempt::class)->qualified($stage, $submission),
            ]);
        }

        if (! $attempt->isInProgress()) {
            return view('competitions.student.received', compact('competition', 'attempt') + ['round' => $stage]);
        }

        return redirect()->route('competitions.student.unit', [$slug, $stage->uuid, $this->firstOpenUnit($attempt)]);
    }

    public function start(Request $request, string $slug, string $round, StartAttempt $start): RedirectResponse
    {
        [, $stage] = $this->context($request, $slug, $round);

        try {
            $start->handle($this->user($request), $stage);
        } catch (AuthorizationException $exception) {
            return redirect()->route('competitions.student.round', [$slug, $stage->uuid])->with('error', $exception->getMessage() ?: __('competitions.student.round_closed'));
        }

        return redirect()->route('competitions.student.round', [$slug, $stage->uuid]);
    }

    public function unit(Request $request, string $slug, string $round, int $number): View|RedirectResponse
    {
        [$competition, $stage, $submission] = $this->context($request, $slug, $round);
        $attempt = $this->attempt($request, $stage, $submission);

        if ($attempt === null || ! $attempt->isInProgress()) {
            return redirect()->route('competitions.student.round', [$slug, $stage->uuid]);
        }

        $unit = $attempt->layout[$number - 1] ?? null;
        abort_if($unit === null, 404);

        $bank = RoundQuestions::load($stage);

        return view('competitions.student.unit', [
            'competition' => $competition,
            'round' => $stage,
            'attempt' => $attempt,
            'unit' => $unit,
            'number' => $number,
            'total' => count($attempt->layout),
            'questions' => $bank['questions'],
            'passage' => $unit['passage'] !== null ? $bank['passages']->get($unit['passage']) : null,
            'offset' => $this->questionOffset($attempt, $number - 1),
        ]);
    }

    /**
     * يحفظ إجابات الوحدة ثم ينتقل: السابقة، أو التالية، أو رقم وحدة، أو المراجعة، أو التسليم (الآلي عند
     * انتهاء العداد). وانتهاء الوقت يسلّم المحاولة بما حُفظ.
     */
    public function save(Request $request, string $slug, string $round, int $number, SaveAttemptAnswers $save, SubmitAttempt $submit): RedirectResponse
    {
        $request->validate([
            'answers' => ['nullable', 'array', 'max:200'],
            'go' => ['required', 'string', 'max:10'],
        ]);

        [, $stage, $submission] = $this->context($request, $slug, $round);
        $attempt = $this->attempt($request, $stage, $submission);
        abort_if($attempt === null, 404);

        $back = redirect()->route('competitions.student.round', [$slug, $stage->uuid]);

        // سُلّمت قبل هذا الطلب: بانتهاء الوقت (هنا أو في المهمة المجدولة) أو بيد الطالب
        if (! $attempt->isInProgress()) {
            return $back->with('error', $attempt->auto_submitted ? __('competitions.student.time_up') : __('competitions.student.already_submitted'));
        }

        try {
            $save->handle($this->user($request), $attempt, $number - 1, (array) $request->input('answers', []));
        } catch (AuthorizationException $exception) {
            return $back->with('error', $exception->getMessage());
        }

        $go = (string) $request->input('go');

        if ($go === 'submit') {
            $submit->handle($this->user($request), $attempt, auto: $request->boolean('auto'));

            return $back;
        }

        $total = count($attempt->layout);
        $target = match (true) {
            $go === 'prev' => max(1, $number - 1),
            $go === 'next' => $number + 1,
            ctype_digit($go) => (int) $go,
            default => null,
        };

        if ($target === null || $target < 1 || $target > $total) {
            return redirect()->route('competitions.student.review', [$slug, $stage->uuid]);
        }

        return redirect()->route('competitions.student.unit', [$slug, $stage->uuid, $target]);
    }

    public function review(Request $request, string $slug, string $round): View|RedirectResponse
    {
        [$competition, $stage, $submission] = $this->context($request, $slug, $round);
        $attempt = $this->attempt($request, $stage, $submission);

        if ($attempt === null || ! $attempt->isInProgress()) {
            return redirect()->route('competitions.student.round', [$slug, $stage->uuid]);
        }

        return view('competitions.student.review', ['competition' => $competition, 'round' => $stage, 'attempt' => $attempt]);
    }

    public function submit(Request $request, string $slug, string $round, SubmitAttempt $submit): RedirectResponse
    {
        [, $stage, $submission] = $this->context($request, $slug, $round);
        $attempt = $this->attempt($request, $stage, $submission);
        abort_if($attempt === null, 404);

        $submit->handle($this->user($request), $attempt, auto: $request->boolean('auto'));

        return redirect()->route('competitions.student.round', [$slug, $stage->uuid]);
    }

    /**
     * أول وحدة فيها سؤال بلا إجابة، أو الأولى إن أجاب الكل (فالمراجعة منها).
     */
    private function firstOpenUnit(CompetitionAttempt $attempt): int
    {
        foreach ($attempt->layout as $index => $unit) {
            foreach ($unit['questions'] as $question) {
                if ($attempt->answer((int) $question['id']) === null) {
                    return $index + 1;
                }
            }
        }

        return 1;
    }

    /**
     * عدد الأسئلة قبل الوحدة، لترقيم أسئلتها من الإجمالي.
     */
    private function questionOffset(CompetitionAttempt $attempt, int $index): int
    {
        return array_sum(array_map(fn (array $unit): int => count($unit['questions']), array_slice($attempt->layout, 0, $index)));
    }

    /**
     * محاولة الطالب نفسه في المرحلة، والجارية التي انتهى وقتها تُسلَّم آلياً هنا قبل أي عرض.
     */
    private function attempt(Request $request, CompetitionRound $round, CompetitionSubmission $submission): ?CompetitionAttempt
    {
        /** @var CompetitionAttempt|null $attempt */
        $attempt = $round->attempts()->where('submission_id', $submission->id)->where('user_id', $this->user($request)->id)->first();

        if ($attempt !== null && $attempt->isInProgress() && $attempt->expired()) {
            $attempt = app(SubmitAttempt::class)->handle(null, $attempt, auto: true);
        }

        $attempt?->setRelation('round', $round);

        return $attempt;
    }

    private function competition(string $slug): Competition
    {
        return Competition::query()->published()->where('student_mode', true)->where('slug', $slug)->firstOrFail();
    }

    /**
     * المسابقة والمرحلة وتسجيل الطالب نفسه فيها، أو 404. فلا يصل أحد إلى مرحلة لم يسجل في مسابقتها.
     *
     * @return array{0: Competition, 1: CompetitionRound, 2: CompetitionSubmission}
     */
    private function context(Request $request, string $slug, string $round): array
    {
        $competition = $this->competition($slug);

        /** @var CompetitionRound $stage */
        $stage = $competition->rounds()->where('uuid', $round)->firstOrFail();
        $stage->setRelation('competition', $competition);

        $submission = $this->registration($request, $competition);
        abort_if($submission === null, 404);

        return [$competition, $stage, $submission];
    }

    private function registration(Request $request, Competition $competition): ?CompetitionSubmission
    {
        /** @var CompetitionSubmission|null */
        return $competition->submissions()->where('user_id', $this->user($request)->id)->whereNotNull('registered_at')->first();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
