<?php

namespace App\Policies;

use App\Models\CompetitionAttempt;
use App\Models\User;

/**
 * محاولات الطلاب في اللوحة (النتائج): يطّلع عليها من له submissions.view. ولا تُنشأ ولا تُعدَّل ولا تُحذف من
 * اللوحة، فالدرجة من التصحيح الآلي، والتأهيل والنشر والسحب بإجراءاتها. والطالب محاولته وحدها في مساراته.
 */
class CompetitionAttemptPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('submissions.view');
    }

    public function view(User $user, CompetitionAttempt $attempt): bool
    {
        return $user->can('submissions.view');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, CompetitionAttempt $attempt): bool
    {
        return false;
    }

    public function delete(User $user, CompetitionAttempt $attempt): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
