<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\SubmissionReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * اعتماد النتائج: بعد إغلاق التقديم وإنهاء كل تحكيم مسند، تُحسب الدرجة النهائية لكل مشاركة (متوسط
 * مجاميع محكّميها)، وتُقفل الدرجات فلا إدخال ولا تعديل بعده (SubmissionReviewPolicy وScoreSubmission).
 */
class ApproveResults
{
    public function __construct(private GradeMultipleChoice $grade) {}

    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition): Competition
    {
        if (! $actor->can('competitions.approve')) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($actor, $competition): void {
            // الفحوص بعد القفل وعلى النسخة المقفولة: لا اعتمادان متزامنان، ولا درجة تُحفظ أثناءه
            $locked = Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail();

            if ($locked->resultsApproved()) {
                throw new AuthorizationException;
            }

            if ($locked->closes_at->isFuture()) {
                throw ValidationException::withMessages(['competition' => __('competitions.reviews.still_open')]);
            }

            if ($locked->reviews()->whereNull('completed_at')->exists()) {
                throw ValidationException::withMessages(['competition' => __('competitions.reviews.incomplete')]);
            }

            // ما لم يصله التصحيح الآلي بعد (طابور متأخر وقت الذروة) يُصحح هنا قبل القفل
            $locked->submissions()->where('status', SubmissionStatus::Submitted)->whereNull('auto_score')->lazyById()
                ->each(fn (CompetitionSubmission $submission) => $this->grade->handle($submission));

            // الدرجة النهائية لكل المشاركات في استعلام واحد: متوسط مجاميع محكّميها، أو لا شيء بلا تحكيم
            $averages = SubmissionReview::query()
                ->selectRaw('submission_id, round(avg(total), 2) as average')
                ->whereNotNull('total')
                ->whereIn('submission_id', $locked->submissions()->select('id'))
                ->groupBy('submission_id');

            CompetitionSubmission::query()
                ->where('competition_id', $competition->id)
                ->where('status', SubmissionStatus::Submitted)
                ->update(['final_score' => SubmissionReview::query()->fromSub($averages, 'averages')->select('average')->whereColumn('averages.submission_id', 'competition_submissions.id')]);

            $competition->results_approved_at = now();
            $competition->results_approved_by = $actor->id;
            $competition->save();
        });

        activity('competitions')->performedOn($competition)->causedBy($actor)->event(ActivityEvent::ResultsApproved->value)->log(ActivityEvent::ResultsApproved->value);

        return $competition;
    }
}
