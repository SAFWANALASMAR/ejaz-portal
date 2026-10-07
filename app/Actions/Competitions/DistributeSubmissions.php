<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\SubmissionReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * التوزيع الآلي المتوازن: لكل مشاركة مرسلة ينقصها محكّمون، يُسند الأقل عبئاً من محكّمي المسابقة
 * ممن لم تُسند إليه، حتى يبلغ عددهم المطلوب. فيتقارب عبء المحكّمين، ولا يتكرر محكّم في مشاركة.
 */
class DistributeSubmissions
{
    /**
     * @return int عدد الإسنادات الجديدة
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, int $perSubmission): int
    {
        if (! $actor->can('submissions.assign') || $competition->resultsApproved()) {
            throw new AuthorizationException;
        }

        $reviewerIds = $competition->reviewers()->pluck('users.id')->all();

        if ($competition->criteria()->doesntExist()) {
            throw ValidationException::withMessages(['per_submission' => __('competitions.reviews.no_criteria')]);
        }

        if ($perSubmission < 1 || $perSubmission > count($reviewerIds)) {
            throw ValidationException::withMessages(['per_submission' => __('competitions.reviews.per_submission', ['max' => count($reviewerIds)])]);
        }

        $created = DB::transaction(function () use ($competition, $reviewerIds, $perSubmission): int {
            // قفل المسابقة: توزيعان متزامنان لا يُسندان المشاركة نفسها مرتين، ولا توزيع بعد الاعتماد
            if (Competition::query()->whereKey($competition->id)->lockForUpdate()->firstOrFail()->resultsApproved()) {
                throw new AuthorizationException;
            }

            $assigned = SubmissionReview::query()
                ->whereIn('submission_id', $competition->submissions()->select('id'))
                ->get(['submission_id', 'reviewer_id'])
                ->groupBy('submission_id')
                ->map(fn ($reviews): array => $reviews->pluck('reviewer_id')->all());

            $load = array_fill_keys($reviewerIds, 0);

            foreach ($assigned->flatten() as $reviewerId) {
                if (isset($load[$reviewerId])) {
                    $load[$reviewerId]++;
                }
            }

            $rows = [];
            $now = now();

            $competition->submissions()->where('status', SubmissionStatus::Submitted)->orderBy('id')->select('id')->lazyById()
                ->each(function ($submission) use ($assigned, &$load, $perSubmission, &$rows, $now): void {
                    $current = $assigned->get($submission->id, []);

                    while (count($current) < $perSubmission) {
                        $candidates = array_diff_key($load, array_flip($current));

                        if ($candidates === []) {
                            return;
                        }

                        asort($candidates);
                        $reviewerId = (int) array_key_first($candidates);

                        $rows[] = ['submission_id' => $submission->id, 'reviewer_id' => $reviewerId, 'created_at' => $now, 'updated_at' => $now];
                        $current[] = $reviewerId;
                        $load[$reviewerId]++;
                    }
                });

            // إدراج بالدفعات: عشرات الآلاف من الإسنادات في استعلامات قليلة
            foreach (array_chunk($rows, 1000) as $batch) {
                SubmissionReview::query()->insert($batch);
            }

            return count($rows);
        });

        // الإدراج بالدفعات لا يمر بالنموذج، فتُسجَّل العملية كلها سطراً واحداً
        activity('competitions')->performedOn($competition)->causedBy($actor)
            ->event(ActivityEvent::SubmissionsDistributed->value)
            ->withProperties(['per_submission' => $perSubmission, 'created' => $created])
            ->log(ActivityEvent::SubmissionsDistributed->value);

        return $created;
    }
}
