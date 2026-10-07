<?php

namespace App\Actions\Competitions;

use App\Enums\SubmissionStatus;
use App\Models\Competition;
use App\Models\SubmissionReview;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * الإسناد اليدوي: مشاركات مرسلة من المسابقة إلى محكّمين من محكّميها. والمسند من قبل لا يتكرر.
 */
class AssignReviewers
{
    /**
     * @param  list<int>  $submissionIds
     * @param  list<int>  $reviewerIds
     * @return int عدد الإسنادات الجديدة
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition, array $submissionIds, array $reviewerIds): int
    {
        if (! $actor->can('submissions.assign') || $competition->resultsApproved()) {
            throw new AuthorizationException;
        }

        $reviewers = $competition->reviewers()->whereKey($reviewerIds)->pluck('users.id');
        $submissions = $competition->submissions()->whereKey($submissionIds)->where('status', SubmissionStatus::Submitted)->pluck('id');

        if ($reviewerIds === [] || $reviewers->count() !== count(array_unique($reviewerIds))) {
            throw ValidationException::withMessages(['reviewers' => __('competitions.reviews.not_reviewer')]);
        }

        if ($submissions->count() !== count(array_unique($submissionIds))) {
            throw ValidationException::withMessages(['submissions' => __('competitions.reviews.not_submitted')]);
        }

        return DB::transaction(function () use ($submissions, $reviewers): int {
            $created = 0;

            foreach ($submissions as $submissionId) {
                foreach ($reviewers as $reviewerId) {
                    if (SubmissionReview::query()->where('submission_id', $submissionId)->where('reviewer_id', $reviewerId)->exists()) {
                        continue;
                    }

                    $review = new SubmissionReview;
                    $review->submission_id = $submissionId;
                    $review->reviewer_id = $reviewerId;
                    $review->save();
                    $created++;
                }
            }

            return $created;
        });
    }
}
