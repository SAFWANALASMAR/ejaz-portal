<?php

namespace App\Jobs;

use App\Actions\Competitions\GradeMultipleChoice;
use App\Models\CompetitionSubmission;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * التصحيح الآلي لأسئلة الاختيار من متعدد في الخلفية بعد إرسال المشاركة (requirements/06).
 */
class GradeSubmission implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $submissionId) {}

    public function handle(GradeMultipleChoice $grade): void
    {
        $submission = CompetitionSubmission::find($this->submissionId);

        if ($submission !== null) {
            $grade->handle($submission);
        }
    }
}
