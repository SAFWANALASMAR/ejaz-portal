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
    public function cancel(User $user, ProgramRegistration $registration): bool
    {
        return $registration->user_id === $user->id
            && $registration->status !== RegistrationStatus::Cancelled
            && $registration->program->starts_at->isFuture();
    }
}
