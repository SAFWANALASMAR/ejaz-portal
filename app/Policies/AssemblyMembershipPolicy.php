<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * سجل أعضاء الجمعية العمومية (صلاحيات assembly_members.*). الإنشاء والتعديل يمنحان الدور أو يسحبانه،
 * فلا يُربطان بدور (المشرف العام وحده). والعضوية لا تُحذف: تنتهي بحالتها، فيبقى تاريخها.
 */
class AssemblyMembershipPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'assembly_members';
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, Model $record): bool
    {
        return false;
    }

    public function restoreAny(User $user): bool
    {
        return false;
    }
}
