<?php

namespace App\Policies;

use App\Models\User;

/**
 * البرامج ومسجلوها (صلاحيات programs.*): الاطلاع على المسجلين مع البرنامج، وتسجيل حضورهم
 * بالتحديث، وتصديرهم بصلاحية مستقلة. الحذف ناعم.
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
}
