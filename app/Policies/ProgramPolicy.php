<?php

namespace App\Policies;

use App\Models\Program;
use App\Models\User;

/**
 * البرامج ومسجلوها (صلاحيات programs.*): الاطلاع على المسجلين مع البرنامج، وتسجيل حضورهم
 * وتصديرهم بصلاحيتين مستقلتين. الحذف ناعم.
 */
class ProgramPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'programs';
    }

    public function export(User $user): bool
    {
        return $this->allows($user, 'export');
    }

    /**
     * تسجيل حضور المسجلين بصلاحية مستقلة (programs.attend)، فيسجله الموظف دون أن يعدّل البرنامج.
     */
    public function recordAttendance(User $user, Program $program): bool
    {
        return $this->allows($user, 'attend');
    }
}
