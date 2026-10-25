<?php

namespace App\Actions\Competitions;

use App\Models\CompetitionAttempt;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\AttemptLayout;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «ابدأ الاختبار» في مرحلة: للمسجل في المسابقة نفسها، في نافذة المرحلة وحدها، والمتأهل إن كانت للمتأهلين.
 * ينشئ المحاولة مرة واحدة بترتيبها العشوائي وموعدها النهائي (أقرب البدء والمدة ونهاية النافذة)، والطلب
 * الثاني يعيد المحاولة نفسها (القيد الفريد)، فالعائد بعد انقطاع يكمل في الوقت الباقي ولا يبدأ من جديد.
 */
class StartAttempt
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionRound $round): CompetitionAttempt
    {
        $competition = $round->competition;
        $submission = $this->registration($user, $round);

        $existing = $round->attempts()->where('submission_id', $submission->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        if (! $competition->is_published || $competition->trashed() || ! $round->isRunning()) {
            throw new AuthorizationException(__('competitions.student.round_closed'));
        }

        if ($round->qualified_only && ! $this->qualified($round, $submission)) {
            throw new AuthorizationException(__('competitions.student.not_qualified'));
        }

        try {
            return DB::transaction(function () use ($user, $round, $submission): CompetitionAttempt {
                // قفل مشترك على المرحلة: تعديل البنك يقفلها قفلاً حصرياً ثم يفحص أول محاولة، فإما ينتظر هذا
                // البدء تعديلاً جارياً حتى يُعتمد (ويُمسح البنك المخزن)، وإما يجد التعديلُ المحاولةَ فيُرفض
                CompetitionRound::query()->whereKey($round->id)->sharedLock()->firstOrFail();

                $layout = AttemptLayout::build($round, (int) $submission->category_id);
                $ids = array_merge(...array_map(fn (array $unit): array => array_column($unit['questions'], 'id'), $layout));

                // بنك الفئة بعدد المرحلة تماماً، فلا يختلف عدد الأسئلة ولا الدرجة القصوى بين الطلاب (مراجعة المشرف)
                if ($ids === [] || count($ids) !== $round->question_count) {
                    throw ValidationException::withMessages(['round' => __('competitions.student.no_questions')]);
                }

                $deadline = now()->addMinutes($round->duration_minutes);
                $attempt = new CompetitionAttempt;
                $attempt->round_id = $round->id;
                $attempt->submission_id = $submission->id;
                $attempt->user_id = $user->id;
                $attempt->started_at = now();
                $attempt->deadline_at = $deadline->min($round->ends_at);
                $attempt->layout = $layout;
                $attempt->answers = [];
                // العدد والدرجة القصوى عند البدء، فيُتحقق من تساويهما قبل الترتيب والسحب (RunTieDraw)
                $attempt->question_count = count($ids);
                $attempt->max_score = (float) CompetitionQuestion::query()->whereIn('id', $ids)->sum('points');
                $attempt->save();

                return $attempt;
            });
        } catch (UniqueConstraintViolationException) {
            return $round->attempts()->where('submission_id', $submission->id)->firstOrFail();
        }
    }

    /**
     * تسجيل الطالب نفسه في مسابقة المرحلة، أو رفض.
     *
     * @throws AuthorizationException
     */
    public function registration(User $user, CompetitionRound $round): CompetitionSubmission
    {
        /** @var CompetitionSubmission|null $submission */
        $submission = CompetitionSubmission::query()
            ->where('competition_id', $round->competition_id)
            ->where('user_id', $user->id)
            ->whereNotNull('registered_at')
            ->first();

        if ($submission === null || ! $round->competition->student_mode || ! $user->is_active) {
            throw new AuthorizationException(__('competitions.student.not_registered'));
        }

        return $submission;
    }

    /**
     * أشّرت اللجنة عليه «متأهلاً» في نتائج المرحلة السابقة.
     */
    public function qualified(CompetitionRound $round, CompetitionSubmission $submission): bool
    {
        $previous = $round->previous();

        return $previous !== null && $previous->attempts()
            ->where('submission_id', $submission->id)
            ->whereNotNull('qualified_at')
            ->exists();
    }
}
