<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionRound;
use App\Models\User;
use App\Support\CompetitionAccess;
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
        if (! CompetitionAccess::managesResults($actor, $round->competition) || ! $round->competition->student_mode) {
            throw new AuthorizationException;
        }

        // الفحوص كلها على صف المرحلة المقفول داخل المعاملة (مراجعة المشرف): مدّ النهاية (SaveCompetitionRound) يقفل
        // الصف نفسه، فإما أن يسبق فيرى النشرُ النهايةَ الجديدة، وإما أن يأتي بعد النشر فيرفضه قفل النافذة
        return DB::transaction(function () use ($actor, $round): CompetitionRound {
            $locked = CompetitionRound::query()->whereKey($round->id)->lockForUpdate()->firstOrFail();

            if ($locked->resultsPublished()) {
                return $locked;
            }

            $grace = (int) config('competitions.student.grace_seconds');

            // بعد النهاية وهامش الشبكة: الحفظ مقبول حتى الموعد وهامشه، فلا يُسقط النشرُ آخرَ الإجابات
            if (now()->lte($locked->ends_at->copy()->addSeconds($grace))) {
                throw ValidationException::withMessages(['round' => __('competitions.student.publish_before_end')]);
            }

            // لا نشر ومحاولة جارية في وقتها: لا يُقطع على طالب بقي له وقت. والموعد لا يتجاوز النهاية عند البدء،
            // فلا يقع هذا إلا بخلل، ويُرفض صراحة
            $cutoff = now()->subSeconds($grace);

            if ($locked->attempts()->where('status', AttemptStatus::InProgress)->where('deadline_at', '>=', $cutoff)->exists()) {
                throw ValidationException::withMessages(['round' => __('competitions.student.publish_running')]);
            }

            // وما بقي جارياً وقد انتهى وقته يُسلَّم بما حُفظ، في المعاملة نفسها قبل النشر
            $locked->attempts()->where('status', AttemptStatus::InProgress)->where('deadline_at', '<', $cutoff)->lazyById()->each(
                fn (CompetitionAttempt $attempt) => $this->submit->handle(null, $attempt, auto: true),
            );

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
