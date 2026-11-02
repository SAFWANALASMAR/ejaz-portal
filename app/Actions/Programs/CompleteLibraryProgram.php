<?php

namespace App\Actions\Programs;

use App\Enums\ActivityEvent;
use App\Enums\RegistrationStatus;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Support\LibraryProgramProgress;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * «أتممت» في برنامج مكتبة: لصاحب التسجيل المؤكد وحده، في برنامج منشور لم ينتهِ، وبعد بلوغ النهاية بقواعد الخادم
 * (LibraryProgramProgress)، فلا يكفي ضغط الزر. ثم تُصدر شهادة الإتمام في الطابور بحالة شهادات الحضور نفسها
 * (SyncAttendanceCertificate، ولا إصدار في الإنتاج قبل قالب الجمعية). ويُسجَّل في سجل العمليات.
 */
class CompleteLibraryProgram
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException لم يبلغ النهاية، أو انتهى البرنامج
     */
    public function handle(User $user, ProgramRegistration $registration): ProgramRegistration
    {
        $program = $registration->program;

        if ($registration->user_id !== $user->id || $registration->status !== RegistrationStatus::Confirmed
            || $program->libraryKind() === null || ! $program->is_published || $program->trashed()
            || $program->libraryItem === null || ! $program->libraryItem->is_published || $program->libraryItem->trashed()) {
            throw new AuthorizationException;
        }

        if ($registration->completed_at !== null) {
            return $registration;
        }

        if ($program->ends_at !== null && $program->ends_at->isPast()) {
            throw ValidationException::withMessages(['complete' => __('programs.library.ended')]);
        }

        // ومن أوقف «لا تحفظ قراءاتي» لا يُحصى تقدّمه فلا يُتمّ، ويُطلب منه تفعيله (مراجعة المشرف على #126، م٢-ج)
        if ($user->reading_tracking_disabled_at !== null) {
            throw ValidationException::withMessages(['complete' => __('programs.library.tracking_off')]);
        }

        if (! LibraryProgramProgress::for($program, $user, $registration)->complete()) {
            throw ValidationException::withMessages(['complete' => __('programs.library.not_yet')]);
        }

        DB::transaction(function () use ($user, $registration): void {
            $locked = ProgramRegistration::query()->whereKey($registration->id)->lockForUpdate()->firstOrFail();

            // وإلغاء متزامن قبل القفل
            if ($locked->completed_at !== null || $locked->status !== RegistrationStatus::Confirmed) {
                return;
            }

            $locked->forceFill(['completed_at' => now()])->save();
            $registration->setRawAttributes($locked->getAttributes(), true);

            activity('programs')->causedBy($user)->performedOn($locked)
                ->event(ActivityEvent::ProgramCompleted->value)
                ->withProperties(['program' => $registration->program_id])
                ->log(ActivityEvent::ProgramCompleted->value);

            DB::afterCommit(fn () => SyncAttendanceCertificate::dispatch($locked));
        });

        return $registration;
    }
}
