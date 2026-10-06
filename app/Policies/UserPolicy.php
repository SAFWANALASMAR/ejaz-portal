<?php

namespace App\Policies;

use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * المستخدمون: بيانات شخصية، فلا يصلها إلا من له users.*.
 * والمنع الصريح هنا (false) يسري على المشرف العام أيضاً (Gate::after):
 * - لا يُعدَّل حساب أُخفيت بياناته، ولا تُمنح له أدوار.
 * - لا يحذف أحد حسابه.
 * - وغير المشرف العام لا يعدّل حساباً له دور إداري.
 * ومنح الأدوار للمشرف العام وحده، قاعدةً في الشيفرة لا في ملف الصلاحيات.
 */
class UserPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'users';
    }

    public function update(User $user, Model $record): bool
    {
        if ($record instanceof User && $record->anonymized_at !== null) {
            return false;
        }

        return $this->allows($user, 'update') && ! $this->protectsAdministrator($user, $record);
    }

    public function delete(User $user, Model $record): bool
    {
        return $user->isNot($record)
            && $this->allows($user, 'delete')
            && ! $this->protectsAdministrator($user, $record);
    }

    public function assignRoles(User $user, ?User $record = null): bool
    {
        return $user->hasRole(RoleName::SuperAdmin->value) && $record?->anonymized_at === null;
    }

    /**
     * إخفاء البيانات (معالجة طلب الحذف). وشروط الحساب نفسه في AnonymizeUser::eligible.
     */
    public function anonymize(User $user, User $record): bool
    {
        return $this->allows($user, 'delete') && AnonymizeUser::eligible($user, $record);
    }

    /**
     * حساب له دور إداري لا يعدّله إلا المشرف العام.
     */
    private function protectsAdministrator(User $user, Model $record): bool
    {
        return $record instanceof User
            && $record->hasAnyRole(RoleName::values(RoleName::panelRoles()))
            && ! $user->hasRole(RoleName::SuperAdmin->value);
    }
}
