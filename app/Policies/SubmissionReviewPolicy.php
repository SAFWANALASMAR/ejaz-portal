<?php

namespace App\Policies;

use App\Models\SubmissionReview;
use App\Models\User;

/**
 * التحكيم: المحكّم يرى ما أُسند إليه وحده ويُدخل درجاته حتى اعتماد النتائج. ومسؤول المسابقات يطّلع
 * على كل التحكيمات (submissions.view) ولا يُدخل درجات أحد. والمنع صريح فلا يغيّره Gate::after.
 */
class SubmissionReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reviews.view') || $user->can('submissions.view');
    }

    public function view(User $user, SubmissionReview $review): bool
    {
        return ($review->reviewer_id === $user->id && $user->can('reviews.view')) || $user->can('submissions.view');
    }

    public function update(User $user, SubmissionReview $review): bool
    {
        return $review->reviewer_id === $user->id
            && $user->can('reviews.update')
            && ! $review->loadMissing('submission.competition')->submission->competition->resultsApproved();
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, SubmissionReview $review): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
