<?php

namespace App\Actions\Competitions;

use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionQuestion;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * تسليم المحاولة وتصحيحها آلياً: من الطالب («تسليم الإجابات»)، أو آلياً عند انتهاء الوقت (المتصفح، أو أول
 * طلب بعد الموعد، أو ejaz:close-expired-attempts). يقفل الصف، والمسلَّمة تُعاد كما هي بلا أثر، فالتسليم
 * متكرر الأمان. والدرجة تُحفظ ولا تُعرض للطالب حتى تُنشر نتائج المرحلة. والمقالي يُصحَّح يدوياً.
 */
class SubmitAttempt
{
    /**
     * @param  User|null  $user  null للتسليم الآلي من المهمة المجدولة
     *
     * @throws AuthorizationException
     */
    public function handle(?User $user, CompetitionAttempt $attempt, bool $auto = false): CompetitionAttempt
    {
        if ($user !== null && $attempt->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($attempt, $auto): CompetitionAttempt {
            /** @var CompetitionAttempt $locked */
            $locked = CompetitionAttempt::query()->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress()) {
                return $locked;
            }

            $late = now()->gt($locked->deadline_at);
            [$score, $max] = $this->grade($locked);

            $locked->status = AttemptStatus::Submitted;
            $locked->auto_submitted = $auto || $late;
            $locked->submitted_at = $late ? $locked->deadline_at : now();
            $locked->score = (float) $score;
            $locked->max_score = (float) $max;
            $locked->save();

            return $locked;
        });
    }

    /**
     * الدرجة والدرجة العظمى: مجموع درجات الأسئلة الموضوعية التي طابقت إجابتها الخيار الصحيح، من البنك
     * مباشرة (الخيار الصحيح لا يُخزَّن في الذاكرة المؤقتة). والعظمى تشمل درجات المقالي.
     *
     * @return array{0: int, 1: int}
     */
    public function grade(CompetitionAttempt $attempt): array
    {
        $questions = CompetitionQuestion::query()->whereIn('id', $attempt->questionIds())->get(['id', 'type', 'correct_option', 'points']);
        $score = 0;
        $max = 0;

        foreach ($questions as $question) {
            $max += $question->points;

            if ($question->type->isChoice() && $question->correct_option !== null && $attempt->answer($question->id) === $question->correct_option) {
                $score += $question->points;
            }
        }

        return [$score, $max];
    }
}
