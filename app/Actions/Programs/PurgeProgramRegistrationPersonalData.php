<?php

namespace App\Actions\Programs;

use App\Enums\ActivityEvent;
use App\Models\ProgramRegistration;
use Illuminate\Support\Facades\DB;

/**
 * حذف إجابات تسجيل من حُذف حسابه في برنامج بعد مدة الاحتفاظ (قرار صفوان على مراجعة #2). ويبقى التسجيل
 * نفسه بحالته وحضوره لإحصاء البرنامج. يستدعيه ejaz:purge-anonymized-data وحده، ولا تراجع عنه.
 */
class PurgeProgramRegistrationPersonalData
{
    public function handle(ProgramRegistration $registration): void
    {
        DB::transaction(function () use ($registration): void {
            /** @var ProgramRegistration $locked */
            $locked = ProgramRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            // الإجابات لا تدخل في سجل النموذج (LogsActivity)
            $locked->forceFill(['answers' => null])->save();

            activity('programs')->performedOn($locked)
                ->event(ActivityEvent::PersonalDataPurged->value)
                ->withProperties(['program_id' => $locked->program_id])
                ->log(ActivityEvent::PersonalDataPurged->value);
        });
    }
}
