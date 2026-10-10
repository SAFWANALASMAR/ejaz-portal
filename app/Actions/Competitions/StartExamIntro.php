<?php

namespace App\Actions\Competitions;

use App\Enums\ExamStage;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\ExamLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * بدء مشاهدة الفيديو التعريفي: يُسجَّل أول تشغيل وحده، فإعادة التشغيل لا تؤخر الموعد. ومنه يُحسب
 * أقرب وقت يُقبل فيه انتهاء المشاهدة (FinishExamIntro).
 */
class StartExamIntro
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, CompetitionSubmission $submission): CompetitionSubmission
    {
        return DB::transaction(function () use ($user, $submission): CompetitionSubmission {
            $locked = ExamLock::stage($user, $submission, ExamStage::Intro);

            if ($locked->intro_started_at === null) {
                $locked->intro_started_at = now();
                $locked->save();
            }

            return $locked;
        });
    }
}
