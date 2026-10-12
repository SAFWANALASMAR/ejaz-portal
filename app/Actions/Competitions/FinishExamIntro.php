<?php

namespace App\Actions\Competitions;

use App\Enums\ExamStage;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\ExamLock;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * انتهاء مشاهدة الفيديو التعريفي «كاملًا»: لا يُقبل قبل مرور مدة المقطع الحقيقية من بدء المشاهدة
 * المسجل (بهامش قصير لتأخر الشبكة)، فلا تُتجاوز المرحلة بطلب مصنوع ولو تجاوز أحد زر الصفحة.
 */
class FinishExamIntro
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission): CompetitionSubmission
    {
        return DB::transaction(function () use ($user, $submission): CompetitionSubmission {
            $locked = ExamLock::stage($user, $submission, ExamStage::Intro);
            $competition = $locked->competition;

            if (blank($competition->intro_video_path)) {
                throw ValidationException::withMessages(['intro' => __('competitions.exam.intro_missing')]);
            }

            $seconds = max(0, (int) $competition->intro_video_seconds - (int) config('competitions.exam.intro_tolerance_seconds'));

            if ($locked->intro_started_at === null || $locked->intro_started_at->copy()->addSeconds($seconds)->isFuture()) {
                throw ValidationException::withMessages(['intro' => __('competitions.exam.intro_not_finished')]);
            }

            $locked->intro_watched_at = now();
            $locked->save();

            return $locked;
        });
    }
}
