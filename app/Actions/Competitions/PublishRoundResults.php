<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «نشر النتائج» لمرحلة (قرار المدير التنفيذي: لا يرى الطالب درجته حتى تُنشر من اللوحة): بعد انتهاء نافذتها،
 * تُسلَّم أولاً المحاولات الجارية التي انتهى وقتها، ثم تظهر الدرجات في حسابات الطلاب. والنشر مرة واحدة،
 * ويُسجَّل في سجل العمليات.
 */
class PublishRoundResults
{
    public function __construct(private SubmitAttempt $submit) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, CompetitionRound $round): CompetitionRound
    {
        if (! $actor->can('competitions.approve') || ! $round->competition->student_mode) {
            throw new AuthorizationException;
        }

        if (! $round->hasEnded()) {
            throw ValidationException::withMessages(['round' => __('competitions.student.publish_before_end')]);
        }

        // ما بقي جارياً بعد النافذة انتهى وقته حتماً (الموعد النهائي لا يتجاوز نهايتها)
        $round->attempts()->where('status', AttemptStatus::InProgress)->each(
            fn (CompetitionAttempt $attempt) => $this->submit->handle(null, $attempt, auto: true),
        );

        return DB::transaction(function () use ($actor, $round): CompetitionRound {
            $locked = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($locked->resultsPublished()) {
                return $locked;
            }

            $locked->results_published_at = now();
            $locked->save();

            activity('competitions')->performedOn($locked)->causedBy($actor)
                ->event(ActivityEvent::RoundResultsPublished->value)
                ->withProperties(['competition_id' => $locked->competition_id, 'submitted' => $locked->submittedCount()])
                ->log(ActivityEvent::RoundResultsPublished->value);

            return $locked;
        });
    }
}
