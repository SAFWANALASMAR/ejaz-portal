<?php

namespace App\Policies;

use App\Models\CompetitionSubmission;
use App\Models\User;

/**
 * المشاركات: المتقدم يرى مشاركته ويعدّلها مسودةً فقط، والإدارة بصلاحيات submissions.*.
 * ولا يعدّل أحد إجابات غيره، ولا المشرف العام (منع صريح لا يغيّره Gate::after).
 */
class CompetitionSubmissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('submissions.view');
    }

    public function view(User $user, CompetitionSubmission $submission): bool
    {
        return $this->owns($user, $submission) || $user->can('submissions.view');
    }

    public function update(User $user, CompetitionSubmission $submission): bool
    {
        // تسجيل مسابقة الطلاب لا يُعدَّل بنموذج المشاركة ولا رحلة الاختبار القديمة (plans/student-competition.md)
        return $this->owns($user, $submission) && $submission->isDraft() && ! $submission->trashed() && ! $submission->competition->student_mode;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function delete(User $user, CompetitionSubmission $submission): bool
    {
        return $user->can('submissions.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('submissions.delete');
    }

    public function restore(User $user, CompetitionSubmission $submission): bool
    {
        return $user->can('submissions.delete');
    }

    public function forceDelete(User $user, CompetitionSubmission $submission): bool
    {
        return false;
    }

    public function forceDeleteAny(User $user): bool
    {
        return false;
    }

    private function owns(User $user, CompetitionSubmission $submission): bool
    {
        return $submission->user_id === $user->id;
    }
}
