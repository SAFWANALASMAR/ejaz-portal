<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Models\CompetitionAttempt;
use App\Models\CompetitionQuestion;
use App\Models\User;
use App\Support\AttemptLock;
use App\Support\RoundQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حفظ إجابات وحدة من الاختبار عند كل انتقال: لكل سؤال من الوحدة خيار من خياراته (أو نص المقالي)، أو لا شيء
 * فيُمسح. لصاحب المحاولة وحده، وهي جارية، وقبل الموعد النهائي بهامش الشبكة. والإجابات تُقرأ من الصف
 * المقفول، فلا يمحو طلب من نافذة أخرى ما حُفظ بينهما. ولا يُكشف شيء عن صحة الإجابة.
 */
class SaveAttemptAnswers
{
    /**
     * @param  array<mixed>  $answers  {معرّف السؤال: مفتاح الخيار أو النص}
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionAttempt $attempt, int $unit, array $answers): CompetitionAttempt
    {
        if ($attempt->user_id !== $user->id) {
            throw new AuthorizationException;
        }

        $questions = RoundQuestions::load($attempt->round)['questions'];
        $layoutUnit = $attempt->layout[$unit] ?? null;

        if ($layoutUnit === null) {
            throw ValidationException::withMessages(['unit' => __('competitions.exam.unknown_question')]);
        }

        $clean = [];

        foreach ($layoutUnit['questions'] as $item) {
            $id = (int) $item['id'];
            $value = $answers[$id] ?? $answers[(string) $id] ?? null;
            $question = $questions->get($id);

            if (! $question instanceof CompetitionQuestion) {
                continue;
            }

            if ($value === null || $value === '') {
                $clean[$id] = null;

                continue;
            }

            if ($question->type === QuestionType::Essay) {
                if (! is_string($value) || mb_strlen($value) > (int) config('competitions.student.essay_max_chars') || str_contains($value, "\0")) {
                    throw ValidationException::withMessages(['answers' => __('competitions.student.essay_too_long')]);
                }

                $clean[$id] = trim($value);

                continue;
            }

            // القيمة رمز الخيار المعتم في هذه المحاولة (CompetitionAttempt::optionToken)
            $key = is_string($value) ? $attempt->optionKey($id, $item['options'], $value) : null;

            if ($key === null) {
                throw ValidationException::withMessages(['answers' => __('validation.in', ['attribute' => __('competitions.entry.answer')])]);
            }

            $clean[$id] = $key;
        }

        return DB::transaction(function () use ($attempt, $clean): CompetitionAttempt {
            $locked = AttemptLock::running($attempt);
            $saved = (array) $locked->answers;

            foreach ($clean as $id => $value) {
                if ($value === null) {
                    unset($saved[(string) $id]);
                } else {
                    $saved[(string) $id] = $value;
                }
            }

            $locked->answers = $saved;
            $locked->save();

            return $locked;
        });
    }
}
