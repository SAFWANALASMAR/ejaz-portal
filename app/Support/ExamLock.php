<?php

namespace App\Support;

use App\Enums\ExamStage;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * فحص كل خطوة في رحلة الاختبار داخل معاملة: المشاركة مشاركة المتقدم نفسه، ومسابقتها اختبار مفتوح،
 * وصفها مقفول مسودةً (SubmissionLock)، والمرحلة المقفولة هي المرحلة المطلوبة. فلا تُتجاوز مرحلة بطلب
 * مصنوع، ولا يكتب طلبان متزامنان أحدهما فوق الآخر.
 */
final class ExamLock
{
    /**
     * @throws AuthorizationException
     */
    public static function stage(User $user, CompetitionSubmission $submission, ExamStage $expected): CompetitionSubmission
    {
        if (! $user->can('update', $submission)) {
            throw new AuthorizationException;
        }

        $competition = $submission->competition;

        if (! $competition->exam_mode) {
            throw new AuthorizationException;
        }

        if (! $competition->isOpen()) {
            throw new AuthorizationException(__('competitions.entry.closed'));
        }

        $locked = SubmissionLock::draft($submission->id);

        if ($locked->examStage() !== $expected) {
            throw new AuthorizationException(__('competitions.exam.wrong_stage'));
        }

        $locked->setRelation('competition', $competition);

        return $locked;
    }
}
