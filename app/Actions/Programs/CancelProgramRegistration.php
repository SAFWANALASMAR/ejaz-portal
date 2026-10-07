<?php

namespace App\Actions\Programs;

use App\Enums\RegistrationStatus;
use App\Models\Program;
use App\Models\ProgramRegistration;
use App\Models\User;
use App\Notifications\ProgramRegistrationChanged;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

/**
 * إلغاء المستخدم تسجيله قبل بدء البرنامج. المقعد المحرر يذهب لأقدم المنتظرين في المعاملة نفسها
 * (بقفل سطر البرنامج كالتسجيل)، ويُشعَر بالبريد.
 */
class CancelProgramRegistration
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $user, ProgramRegistration $registration): void
    {
        if (! $user->can('cancel', $registration)) {
            throw new AuthorizationException;
        }

        DB::transaction(function () use ($registration): void {
            /** @var Program $program */
            $program = Program::withTrashed()->whereKey($registration->program_id)->lockForUpdate()->firstOrFail();

            /** @var ProgramRegistration $locked */
            $locked = ProgramRegistration::query()->whereKey($registration->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->status === RegistrationStatus::Cancelled) {
                return;
            }

            $freesSeat = $locked->status === RegistrationStatus::Confirmed;
            $locked->update(['status' => RegistrationStatus::Cancelled, 'cancelled_at' => now()]);
            $registration->setRawAttributes($locked->getAttributes(), true);

            if (! $freesSeat || ! $program->has_waitlist || ! $program->hasFreeSeat()) {
                return;
            }

            $next = ProgramRegistration::query()
                ->where('program_id', $program->id)
                ->where('status', RegistrationStatus::Waitlisted->value)
                ->orderBy('updated_at')
                ->orderBy('id')
                ->lockForUpdate()
                ->first();

            if ($next !== null) {
                $next->update(['status' => RegistrationStatus::Confirmed]);
                $next->user->notify(new ProgramRegistrationChanged($next, ProgramRegistrationChanged::PROMOTED));
            }
        });
    }
}
