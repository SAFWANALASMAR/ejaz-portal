<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRetake;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Notifications\CompetitionRetakeGranted;
use App\Support\CompetitionAccess;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «السماح بإعادة الاختبار» لطالب بعينه في مرحلة (قرار صفوان، 13 نوفمبر 2026 —
 * docs/decisions/2026-11-13-feature-competition-retake.md): للمدير التنفيذي والمسؤول التقني وحدهما
 * (competition.allow_retake)، بسبب مكتوب. المحاولة المسلَّمة تبقى في النتائج حتى يبدأ الطالب الإعادة فعلاً، فتُحفظ كما
 * كانت في CompetitionRetake وتُرفع (StartAttempt)، فإن لم يبدأ قبل المهلة بقيت درجته الأصلية (مراجعة #261، ت١). ويبدأ في
 * نافذة المرحلة، أو حتى المهلة المحددة إن أُغلقت، ولا إعادة قبل اعتماد المواعيد ولا بعد نشر النتائج. ويُبلَّغ بالبريد،
 * وتظهر له في «حسابي» بطاقة البدء.
 */
class GrantCompetitionRetake
{
    public const PERMISSION = 'competition.allow_retake';

    /** أبعد مهلة بدء تُقبل بعد إغلاق النافذة. */
    public const MAX_DAYS = 30;

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

        $competition = $round->competition;

        if ($competition === null || $competition->trashed() || ! $competition->student_mode) {
            throw new AuthorizationException(__('competitions.student.admin.not_allowed'));
        }

        // ما يرفض StartAttempt البدء فيه لا تُمنح فيه إعادة: مسابقة غير منشورة، أو مواعيد لم تُعتمد، أو تسجيل ملغى
        if (! $competition->is_published || $competition->schedule_pending || $attempt->submission === null || $attempt->submission->trashed()) {
            throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.unavailable')]);
        }

        // بعد إغلاق النافذة لا إعادة بلا مهلة، والمهلة في المستقبل وفي حد MAX_DAYS
        if ($until !== null && ($until->isPast() || $until->gt(now()->addDays(self::MAX_DAYS)))) {
            throw ValidationException::withMessages(['until' => __('competitions.student.retake.until_range', ['days' => self::MAX_DAYS])]);
        }

        if ($until === null && ! $round->isRunning()) {
            throw ValidationException::withMessages(['until' => __('competitions.student.retake.until_required')]);
        }

        $retake = DB::transaction(function () use ($actor, $attempt, $reason, $until, $round): CompetitionRetake {
            // صف المرحلة مقفول كما يقفله النشر (PublishRoundResults)، فلا تُمنح إعادة في مرحلة نُشرت بعد الفحص (مراجعة #261، م١)
            $lockedRound = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($lockedRound->resultsPublished()) {
                throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.results_published')]);
            }

            $locked = CompetitionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            // الجارية يكملها الطالب بنفسه
            if ($locked === null || $locked->status !== AttemptStatus::Submitted) {
                throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.not_submitted')]);
            }

            // وإعادة واحدة قائمة لكل طالب في المرحلة
            if (CompetitionRetake::query()->active($lockedRound)->where('submission_id', $locked->submission_id)->exists()) {
                throw ValidationException::withMessages(['attempt' => __('competitions.student.retake.already_granted')]);
            }

            $retake = new CompetitionRetake;
            $retake->round_id = $locked->round_id;
            $retake->submission_id = $locked->submission_id;
            $retake->user_id = $locked->user_id;
            $retake->granted_by = $actor->id;
            $retake->reason = $reason;
            $retake->retake_until = $until !== null ? Carbon::instance($until) : null;
            $retake->save();

            return $retake;
        });

        $retake->loadMissing('round.competition');
        $student = User::query()->find($retake->user_id);
        $student?->notify(new CompetitionRetakeGranted($retake));

        return $retake;
    }
}
