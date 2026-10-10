<?php

namespace App\Console\Commands;

use App\Enums\SubmissionStatus;
use App\Jobs\GradeSubmission;
use App\Models\Competition;
use Illuminate\Console\Command;

/**
 * يعيد التصحيح الآلي لكل مشاركات مسابقة مرسلة، في الطابور. لا أثر له بعد اعتماد النتائج.
 */
class GradeCompetition extends Command
{
    protected $signature = 'ejaz:grade-competition {slug : معرّف المسابقة في الرابط}';

    protected $description = 'إعادة التصحيح الآلي لمشاركات مسابقة';

    public function handle(): int
    {
        $competition = Competition::query()->where('slug', (string) $this->argument('slug'))->first();

        if ($competition === null) {
            $this->error('لا مسابقة بهذا المعرّف.');

            return self::FAILURE;
        }

        $count = 0;

        $competition->submissions()->where('status', SubmissionStatus::Submitted)->select('id')->lazyById()->each(function ($submission) use (&$count): void {
            GradeSubmission::dispatch($submission->id);
            $count++;
        });

        $this->info("أُرسلت {$count} مشاركة إلى التصحيح.");

        return self::SUCCESS;
    }
}
