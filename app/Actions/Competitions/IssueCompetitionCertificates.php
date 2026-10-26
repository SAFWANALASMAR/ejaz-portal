<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Enums\AttemptStatus;
use App\Jobs\GenerateCompetitionCertificate;
use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionAccess;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * «إصدار الشهادات»: شهادة لكل مشارك سلّم في مرحلة واحدة على الأقل، تُولَّد في الطابور وتظهر في حسابه. وإعادة
 * الإصدار (بعد تبديل القالب) تولّدها من جديد. ولا تُصدر في الإنتاج بلا قالب الجمعية.
 *
 * يعيد عدد الشهادات المرسلة إلى الطابور.
 */
class IssueCompetitionCertificates
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function handle(User $actor, Competition $competition): int
    {
        if (! CompetitionAccess::managesResults($actor, $competition) || ! $competition->student_mode) {
            throw new AuthorizationException;
        }

        if (app()->isProduction() && blank($competition->certificate_background)) {
            throw ValidationException::withMessages(['certificate_background' => __('competitions.student.certificate_needs_template')]);
        }

        $count = 0;

        $competition->submissions()
            ->whereNotNull('registered_at')
            ->whereHas('attempts', fn ($query) => $query->where('status', AttemptStatus::Submitted))
            ->select('id')
            ->chunkById(500, function ($submissions) use (&$count): void {
                foreach ($submissions as $submission) {
                    GenerateCompetitionCertificate::dispatch($submission->id);
                    $count++;
                }
            });

        activity('competitions')->performedOn($competition)->causedBy($actor)
            ->event(ActivityEvent::CertificatesIssued->value)
            ->withProperties(['count' => $count])
            ->log(ActivityEvent::CertificatesIssued->value);

        return $count;
    }
}
