<?php

namespace App\Actions\Competitions;

use App\Models\Competition;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * مشاركة المتقدم في المسابقة: الموجودة، أو مسودة جديدة. وتقديم واحد لكل متقدم بقيد فريد في قاعدة
 * البيانات، فإن سبقه طلب متزامن أنشأها أعاد الإجراء التي أنشأها ذاك، بلا خطأ ولا تكرار.
 */
class StartSubmission
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, Competition $competition): CompetitionSubmission
    {
        // مسابقة الطلاب تسجيلها ومحاولاتها في مساراتها (RegisterStudent وStartAttempt)، لا في نموذج المشاركة
        if ($competition->student_mode) {
            throw new AuthorizationException;
        }

        $existing = $this->existing($user, $competition);

        if ($existing !== null) {
            return $existing;
        }

        if (! $competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        if (! $user->is_active || (! $user->hasVerifiedEmail() && ! $user->hasVerifiedPhone())) {
            throw new AuthorizationException(__('competitions.entry.verify_phone'));
        }

        try {
            // نقطة حفظ داخل أي معاملة قائمة، فلا يُفسد خطأ القيد الفريد ما بعده
            return DB::transaction(function () use ($user, $competition): CompetitionSubmission {
                $submission = new CompetitionSubmission;
                $submission->competition()->associate($competition);
                $submission->user()->associate($user);
                $submission->save();

                return $submission;
            });
        } catch (UniqueConstraintViolationException) {
            return $this->existing($user, $competition) ?? throw new AuthorizationException(__('competitions.entry.removed'));
        }
    }

    /**
     * المشاركة المحذوفة من الإدارة لا تُعاد ولا يُنشأ بدلها (القيد الفريد يشملها).
     *
     * @throws AuthorizationException
     */
    protected function existing(User $user, Competition $competition): ?CompetitionSubmission
    {
        $submission = $competition->submissions()->withTrashed()->where('user_id', $user->id)->first();

        if ($submission?->trashed()) {
            throw new AuthorizationException(__('competitions.entry.removed'));
        }

        return $submission;
    }
}
