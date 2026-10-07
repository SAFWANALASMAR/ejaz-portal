<?php

namespace App\Actions\Competitions;

use App\Enums\QuestionType;
use App\Enums\SubmissionStatus;
use App\Models\CompetitionQuestion;
use App\Models\CompetitionSubmission;

/**
 * درجة التصحيح الآلي: مجموع درجات أسئلة الاختيار التي طابقت إجابتُها الخيارَ الصحيح. للمرسلة فقط،
 * وتُعاد بلا أثر جانبي (أمر competitions:grade)، ولا تتغير بعد اعتماد النتائج.
 */
class GradeMultipleChoice
{
    public function handle(CompetitionSubmission $submission): void
    {
        $competition = $submission->competition;

        if ($submission->status !== SubmissionStatus::Submitted || $competition->results_approved_at !== null) {
            return;
        }

        $score = $competition->questions
            ->filter(fn (CompetitionQuestion $question): bool => $question->type === QuestionType::MultipleChoice)
            ->sum(fn (CompetitionQuestion $question): int => $question->correct_option !== null && $submission->answer($question) === $question->correct_option ? $question->points : 0);

        $submission->auto_score = (float) $score;
        $submission->save();
    }
}
