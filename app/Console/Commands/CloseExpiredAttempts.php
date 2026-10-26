<?php

namespace App\Console\Commands;

use App\Actions\Competitions\SubmitAttempt;
use App\Enums\AttemptStatus;
use App\Models\CompetitionAttempt;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;

/**
 * التسليم الآلي لمحاولات مسابقة الطلاب التي انتهى وقتها ولم يعد صاحبها (انقطع اتصاله أو أغلق المتصفح)،
 * فتُصحَّح وتُحسب في النتائج. كل دقيقة في المجدول (routes/console.php)، وبلا أثر لما سُلّم.
 */
class CloseExpiredAttempts extends Command
{
    protected $signature = 'ejaz:close-expired-attempts';

    protected $description = 'تسليم محاولات مسابقة الطلاب التي انتهى وقتها وتصحيحها';

    public function handle(SubmitAttempt $submit): int
    {
        $count = 0;
        $cutoff = now()->subSeconds((int) config('competitions.student.grace_seconds'));

        CompetitionAttempt::query()
            ->where('status', AttemptStatus::InProgress)
            ->where('deadline_at', '<', $cutoff)
            ->chunkById(200, function (Collection $attempts) use ($submit, &$count): void {
                foreach ($attempts as $attempt) {
                    $submit->handle(null, $attempt, auto: true);
                    $count++;
                }
            });

        $this->info((string) $count);

        return self::SUCCESS;
    }
}
