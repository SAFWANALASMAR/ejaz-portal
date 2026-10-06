<?php

namespace App\Policies;

use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * المستخدمون: بيانات شخصية، فلا يصلها إلا من له users.*.
 * وحساب المشرف العام لا يعدّله غير مشرف عام، ولا يوقف أحد حسابه أو يحذفه.
 * والمشرف العام نفسه يملك كل شيء عبر Gate::before، وتبقى حماية النفس في الإجراءات.
 */
class UserPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'users';
    }

    public function update(User $user, Model $record): bool
    {
        return $this->allows($user, 'update') && ! $this->protectsSuperAdmin($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->allows($user, 'delete') && $user->isNot($record) && ! $this->protectsSuperAdmin($user, $record);
    }

    public function assignRoles(User $user, User $record): bool
    {
        return $this->allows($user, 'assign-roles');
    }

    /**
     * إخفاء البيانات (معالجة طلب الحذف). وشروط الحساب نفسه في AnonymizeUser::eligible.
     */
    public function anonymize(User $user, User $record): bool
    {
        return $this->allows($user, 'delete') && AnonymizeUser::eligible($user, $record);
    }

    private function protectsSuperAdmin(User $user, Model $record): bool
    {
        return $record instanceof User
            && $record->hasRole(RoleName::SuperAdmin->value)
            && ! $user->hasRole(RoleName::SuperAdmin->value);
    }
}
