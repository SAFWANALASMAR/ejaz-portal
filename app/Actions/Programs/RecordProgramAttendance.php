<?php

namespace App\Actions\Programs;

use App\Enums\RegistrationStatus;
use App\Jobs\SyncAttendanceCertificate;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * تسجيل حضور المؤكدين في برنامج بدأ، أو إلغاؤه. يُسجَّل في سجل العمليات (LogsActivity)، وتتبعه
 * شهادة الحضور (SyncAttendanceCertificate).
 */
class RecordProgramAttendance
{
    /**
     * @param  Collection<int, ProgramRegistration>  $registrations
     * @return int عدد ما تغيّر
     *
     * @throws AuthorizationException
     * @throws ValidationException البرنامج لم يبدأ
     */
    public function handle(User $actor, Program $program, Collection $registrations, bool $attended): int
    {
        if (! $actor->can('update', $program)) {
            throw new AuthorizationException;
        }

        if ($program->starts_at->isFuture()) {
            throw ValidationException::withMessages(['attended_at' => __('programs.errors.not_started')]);
        }

        return DB::transaction(function () use ($program, $registrations, $attended): int {
            $changed = 0;

            foreach ($registrations as $registration) {
                if ($registration->program_id !== $program->id || $registration->status !== RegistrationStatus::Confirmed) {
                    continue;
                }

                $registration->update(['attended_at' => $attended ? ($registration->attended_at ?? now()) : null]);

                if ($registration->wasChanged('attended_at')) {
                    $changed++;
                    // الشهادة تتبع الحضور: تُولَّد أو تُحذف في الخلفية بعد اعتماد المعاملة
                    SyncAttendanceCertificate::dispatch($registration)->afterCommit();
                }
            }

            return $changed;
        });
    }
}
