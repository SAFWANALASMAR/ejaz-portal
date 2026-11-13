<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRetake;
use App\Models\User;
use App\Notifications\CompetitionRetakeGranted;
use App\Support\CompetitionAccess;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «السماح بإعادة الاختبار» لطالب بعينه في مرحلة (قرار صفوان، 13 نوفمبر 2026 —
 * docs/decisions/2026-11-13-feature-competition-retake.md): للمدير التنفيذي والمسؤول التقني وحدهما
 * (competition.allow_retake)، بسبب مكتوب. المحاولة المسلَّمة تُحفظ كما كانت في CompetitionRetake وتُرفع من المحاولات،
 * فلا تدخل في النتائج ولا الترتيب ولا التصدير، ويبدأ الطالب محاولة جديدة في نافذة المرحلة، أو حتى المهلة المحددة إن
 * أُغلقت. ويُبلَّغ بالبريد، وتظهر له في «حسابي» بطاقة البدء.
 */
class GrantCompetitionRetake
{
    public const PERMISSION = 'competition.allow_retake';

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionAttempt $attempt, string $reason, ?CarbonInterface $until = null): CompetitionRetake
    {
        if (! CompetitionAccess::allows($actor, self::PERMISSION)) {
            throw new AuthorizationException(__('competitions.student.admin.not_allowed'));
        }

        $reason = trim($reason);

        if (mb_strlen($reason) < 3 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages(['reason' => __('competitions.student.retake.reason_invalid')]);
        }

        $round = $attempt->round;

        if ($round->competition === null || $round->competition->trashed() || ! $round->competition->student_mode) {
            throw new AuthorizationException(__('competitions.student.admin.not_allowed'));
        }

        // ولا إعادة بعد نشر نتائج المرحلة: تتغير نتيجة معلنة ويظهر للطالب درجته فور التسليم
        if ($round->resultsPublished()) {
            throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.results_published')]);
        }

        // بعد إغلاق النافذة لا إعادة بلا مهلة، والمهلة في المستقبل وحدها
        if ($until !== null && $until->isPast()) {
            throw ValidationException::withMessages(['until' => __('competitions.student.retake.until_past')]);
        }

        if ($until === null && ! $round->isRunning()) {
            throw ValidationException::withMessages(['until' => __('competitions.student.retake.until_required')]);
        }

        $retake = DB::transaction(function () use ($actor, $attempt, $reason, $until): CompetitionRetake {
            $locked = CompetitionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            // الجارية يكملها الطالب بنفسه، والمحذوفة سبقت إعادتها
            if ($locked === null || $locked->status !== AttemptStatus::Submitted) {
                throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.not_submitted')]);
            }

            $retake = new CompetitionRetake;
            $retake->round_id = $locked->round_id;
            $retake->submission_id = $locked->submission_id;
            $retake->user_id = $locked->user_id;
            $retake->granted_by = $actor->id;
            $retake->reason = $reason;
            $retake->retake_until = $until;
            $retake->archived_attempt = $locked->getAttributes();
            $retake->save();

            $locked->delete();

            return $retake;
        });

        $retake->loadMissing('round.competition');
        $student = User::query()->find($retake->user_id);
        $student?->notify(new CompetitionRetakeGranted($retake));

        return $retake;
    }
}
