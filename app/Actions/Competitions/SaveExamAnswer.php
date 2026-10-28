<?php

namespace App\Actions\Competitions;

use App\Enums\ExamStage;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;
use App\Models\User;
use App\Support\ExamLock;
use App\Support\ExamQuestions;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * حفظ إجابة سؤال في الاختبار عند كل انتقال (السابق، والتالي، ورقم سؤال، والمراجعة): خيار واحد من
 * خيارات السؤال، أو لا شيء فيُمسح. والإجابات تُقرأ من الصف المقفول، فلا يمحو طلب من نافذة أخرى إجابة
 * حُفظت بينهما. ولا يُكشف شيء عن صحة الإجابة.
 */
class SaveExamAnswer
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $user, CompetitionSubmission $submission, int $questionId, ?string $option): CompetitionSubmission
    {
        ExamQuestions::load($submission->competition);

        /** @var CompetitionQuestion|null $question */
        $question = $submission->competition->questions->firstWhere('id', $questionId);

        if ($question === null) {
            throw ValidationException::withMessages(['question' => __('competitions.exam.unknown_question')]);
        }

        // الخيار يصل برمزه في الصفحة (optionToken)، والمفتاح نفسه يُرفض
        $option = $option === null ? null : ($submission->optionKey($question, $option) ?? '');

        if ($option !== null && ! in_array($option, $question->optionKeys(), true)) {
            throw ValidationException::withMessages(['option' => __('validation.in', ['attribute' => __('competitions.entry.answer')])]);
        }

        return DB::transaction(function () use ($user, $submission, $questionId, $option): CompetitionSubmission {
            $locked = ExamLock::stage($user, $submission, ExamStage::Questions);

            $answers = (array) $locked->answers;

            if ($option === null) {
                unset($answers[(string) $questionId]);
            } else {
                $answers[(string) $questionId] = $option;
            }

            $locked->answers = $answers;
            $locked->save();

            return $locked;
        });
    }
}
