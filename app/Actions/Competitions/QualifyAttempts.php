<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * تأهيل طلاب من نتائج مرحلة إلى التي بعدها (المرحلة «للمتأهلين»)، أو إلغاء تأهيلهم. لمحاولات مسلَّمة في المرحلة
 * نفسها وحدها، ومعيار التأهل للجنة (open-questions.md). ويُسجَّل في سجل العمليات بعدد من شملهم.
 *
 * يعيد عدد من تغيّرت حالته.
 */
class QualifyAttempts
{
    /**
     * @param  list<int>  $attemptIds
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, CompetitionRound $round, array $attemptIds, bool $qualified = true): int
    {
        if (! CompetitionAccess::managesResults($actor, $round->competition) || ! $round->competition->student_mode) {
            throw new AuthorizationException;
        }

        // التأهل يكشف النتيجة (صفحة المرحلة التالية تقول «للمتأهلين»)، فبعد نشر نتائج المرحلة وحده
        if (! $round->resultsPublished()) {
            throw ValidationException::withMessages(['round' => __('competitions.student.qualify_before_publish')]);
        }

        $count = CompetitionAttempt::query()
            ->where('round_id', $round->id)
            ->where('status', AttemptStatus::Submitted)
            ->whereIn('id', $attemptIds)
            ->when($qualified, fn ($query) => $query->whereNull('qualified_at'), fn ($query) => $query->whereNotNull('qualified_at'))
            ->update(['qualified_at' => $qualified ? now() : null, 'updated_at' => now()]);

        activity('competitions')->performedOn($round)->causedBy($actor)
            ->event(ActivityEvent::AttemptsQualified->value)
            ->withProperties(['count' => $count, 'qualified' => $qualified])
            ->log(ActivityEvent::AttemptsQualified->value);

        return $count;
    }
}
