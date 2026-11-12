<?php

namespace App\Actions\Competitions;

use App\Models\CompetitionAttempt;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionRound;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\AttemptLayout;
use App\Support\SubmissionReference;
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

        try {
            return DB::transaction(function () use ($user, $round, $competition, $submission): CompetitionAttempt {
                // قفل مشترك على المرحلة، والفحوص كلها على النسخة المقفولة (مراجعة المشرف): تعديل البنك أو النافذة
                // يقفلها قفلاً حصرياً ثم يفحص أول محاولة، فإما ينتظر هذا البدء التعديل حتى يُعتمد، وإما يجد التعديلُ
                // المحاولةَ فيُرفض
                $locked = CompetitionRound::query()->whereKey($round->id)->sharedLock()->firstOrFail();
                $locked->setRelation('competition', $competition);

                // ولا بدء في مرحلة نُشرت نتائجها: تظهر درجة من يسلّم فيها فوراً. ولا بدء و«المواعيد تُعلن لاحقاً» مشغّل: مواعيد المراحل
                // المخزنة غير معتمدة (مراجعة الطلب 254 — docs/decisions/2026-11-12-feature-competition-schedule-pending.md)
                if (! $competition->is_published || $competition->trashed() || $competition->schedule_pending || ! $locked->isRunning() || $locked->resultsPublished()) {
                    throw new AuthorizationException(__('competitions.student.round_closed'));
                }

                // والتسجيل مقفول: تصحيح الفئة (CorrectStudentRegistration) يقفله ثم يفحص أول محاولة، فلا يبدأ الطالب
                // ببنك فئة تتغير معه
                $submission = CompetitionSubmission::query()->whereKey($submission->id)->lockForUpdate()->firstOrFail();

                if ($locked->qualified_only && ! $this->qualified($locked, $submission)) {
                    throw new AuthorizationException(__('competitions.student.not_qualified'));
                }

                // الترتيب التراكمي لكل الفئات معاً يقارن المجاميع، فلا بدء والدرجة القصوى تختلف بين الفئات (مراجعة المشرف)
                if (AttemptLayout::mixedMaxScores($locked) !== []) {
                    throw ValidationException::withMessages(['round' => __('competitions.student.mixed_category_max')]);
                }

                $layout = AttemptLayout::build($locked, (int) $submission->category_id);
                $ids = array_merge([], ...array_map(fn (array $unit): array => array_column($unit['questions'], 'id'), $layout));

                // لكل طالب من الفئة العدد نفسه تماماً، وبنكها الذي لا يكوّنه يرفض البدء (AttemptLayout::shortfall)
                if ($ids === [] || count($ids) !== $locked->question_count || AttemptLayout::shortfall($locked, (int) $submission->category_id) !== null) {
                    throw ValidationException::withMessages(['round' => __('competitions.student.no_questions')]);
                }

                $deadline = now()->addMinutes($locked->duration_minutes);
                $attempt = new CompetitionAttempt;
                $attempt->round_id = $locked->id;
                $attempt->submission_id = $submission->id;
                $attempt->user_id = $user->id;
                $attempt->started_at = now();
                $attempt->deadline_at = $deadline->min($locked->ends_at);
                $attempt->layout = $layout;
                $attempt->answers = [];
                // العدد والدرجة القصوى عند البدء، فيُتحقق من تساويهما قبل الترتيب والسحب (RunTieDraw)
                $attempt->question_count = count($ids);
                $attempt->max_score = (float) CompetitionQuestion::query()->whereIn('id', $ids)->sum('points');
                // رقم المحاولة المرجعي يظهر للطالب بعد التسليم وفي حسابه واللوحة (دليل الدخول). والتصادم بعشرة حروف
                // نادر جداً، فيُولَّد غيره قبل الحفظ، ولا يُخلط بالتسجيل المزدوج الذي يلتقطه القيد الفريد أدناه
                do {
                    $attempt->reference = SubmissionReference::generate();
                } while (CompetitionAttempt::query()->where('reference', $attempt->reference)->exists());
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
