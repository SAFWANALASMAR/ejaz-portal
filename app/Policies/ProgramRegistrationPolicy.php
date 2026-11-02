<?php

namespace App\Policies;

use App\Enums\RegistrationStatus;
use App\Models\ProgramRegistration;
use App\Models\User;

/**
 * التسجيل في برنامج: صاحبه يراه ويلغيه قبل بدء البرنامج. ومسؤول البرامج يرى المسجلين ويسجل
 * حضورهم بصلاحيات البرنامج (ProgramPolicy).
 */
class ProgramRegistrationPolicy
{
    /**
     * المسجلون ببياناتهم الشخصية لمن يملك programs.view (docs/roles-permissions.md).
     */
    public function viewAny(User $user): bool
    {
        return $user->can('programs.view');
    }

    public function view(User $user, ProgramRegistration $registration): bool
    {
        return $user->can('programs.view');
    }

    /**
     * صاحب التسجيل وحده يرى شهادته وينزّلها.
     */
    public function download(User $user, ProgramRegistration $registration): bool
    {
        return $registration->user_id === $user->id && $registration->certificate_path !== null;
    }

    public function cancel(User $user, ProgramRegistration $registration): bool
    {
        return $registration->user_id === $user->id
            && $registration->status !== RegistrationStatus::Cancelled
            && ($registration->program->isLibraryProgram()
                // وبرنامج المكتبة يُلغى ما دام مفتوحاً ولم يُتمّه صاحبه (docs/decisions/2026-11-02-reading-program.md)
                ? $registration->completed_at === null && ($registration->program->ends_at === null || $registration->program->ends_at->isFuture())
                : $registration->program->starts_at->isFuture());
    }
}
