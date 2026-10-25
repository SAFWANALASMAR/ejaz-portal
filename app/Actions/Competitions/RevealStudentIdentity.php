<?php

namespace App\Actions\Competitions;

use App\Enums\ActivityEvent;
use App\Models\CompetitionSubmission;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * الاطلاع الكامل على رقم هوية الطالب وجوال ولي أمره (قرار المدير التنفيذي): لمنشئ المسابقة ومن له
 * competition.view_national_id وحدهم (CompetitionPolicy::viewNationalId)، وكل اطلاع يُسجَّل في سجل العمليات
 * بلا الرقم نفسه. وفي غير ذلك يُعرضان مخفيين إلا آخر أربعة أرقام.
 */
class RevealStudentIdentity
{
    /**
     * @return array{national_id: string|null, guardian_phone: string|null}
     *
     * @throws AuthorizationException
     */
    public function handle(User $actor, CompetitionSubmission $submission): array
    {
        if (! $actor->can('viewNationalId', $submission->competition)) {
            throw new AuthorizationException;
        }

        activity('competitions')->performedOn($submission)->causedBy($actor)
            ->event(ActivityEvent::NationalIdViewed->value)
            ->withProperties(['competition_id' => $submission->competition_id, 'reference' => $submission->reference])
            ->log(ActivityEvent::NationalIdViewed->value);

        return [
            'national_id' => $submission->national_id,
            'guardian_phone' => $submission->guardian_phone,
        ];
    }
}
