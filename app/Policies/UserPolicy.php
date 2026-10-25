<?php

namespace App\Policies;

use App\Actions\Users\AnonymizeUser;
use App\Enums\RoleName;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * المستخدمون: بيانات شخصية، فلا يصلها إلا من له users.*.
 * والمنع الصريح هنا (false) يسري على مديري النظام أيضاً (Gate::after):
 * - لا يُعدَّل حساب أُخفيت بياناته، ولا تُمنح له أدوار.
 * - لا يحذف أحد حسابه.
 * - ولا يعدّل حساباً له دور إداري إلا مديرو النظام (المدير التنفيذي والمسؤول التقني).
 * ومنح الأدوار قاعدة في الشيفرة لا في ملف الصلاحيات (RoleName::assignableBy): لمديري النظام. والمشرف العام يطّلع ولا يغيّر.
 */
class UserPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'users';
    }

    public function view(User $user, Model $record): bool
    {
        return $this->allows($user, 'view') && ! $this->protectsAdministrator($user, $record);
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
        if ($record?->anonymized_at !== null) {
            return false;
        }

        if (RoleName::isAdministrator($user)) {
            return true;
        }

        return RoleName::assignableBy($user) !== []
            && ($this->allows($user, 'create') || $this->allows($user, 'update'))
            && ($record === null || ! $this->protectsAdministrator($user, $record));
    }

    /**
     * دعوة تعيين كلمة المرور: لحساب مفعّل لم يدخل بعد، ممن ينشئ الحسابات أو يعدّلها.
     */
    public function invite(User $user, User $record): bool
    {
        return $record->anonymized_at === null
            && $record->is_active
            && $record->last_login_at === null
            && $record->email !== null
            && ($this->allows($user, 'create') || $this->allows($user, 'update'))
            && ! $this->protectsAdministrator($user, $record);
    }

    /**
     * كلمة مرور جديدة من اللوحة لمن لا يستعيدها بنفسه: لا بريد موثق له ولا جوال موثق (ملاحظات المدير التنفيذي، 25 أكتوبر
     * 2026). للمدير التنفيذي والمسؤول التقني (accounts.update)، فيما يعدّله من الحسابات، ولا لحسابه نفسه.
     */
    public function resetPassword(User $user, User $record): bool
    {
        return $user->isNot($record)
            && $record->anonymized_at === null
            && ! $record->hasVerifiedEmail()
            && ! $record->hasVerifiedPhone()
            && $user->can('accounts.update')
            && $this->update($user, $record);
    }

    /**
     * إخفاء البيانات (معالجة طلب الحذف). وشروط الحساب نفسه في AnonymizeUser::eligible.
     */
    public function anonymize(User $user, User $record): bool
    {
        return $this->allows($user, 'delete') && AnonymizeUser::eligible($user, $record);
    }

    /**
     * حساب له دور إداري لا يعدّله إلا مديرو النظام (المدير التنفيذي والمسؤول التقني)، ويطّلع عليه المشرف العام. ومن
     * سواهم ممن يُمنح users.* يصل إلى حسابات المستفيدين وحدها.
     */
    private function protectsAdministrator(User $user, Model $record): bool
    {
        if (! $record instanceof User || RoleName::isOverseer($user)) {
            return false;
        }

        return array_intersect($record->getRoleNames()->all(), RoleName::values(RoleName::panelRoles())) !== [];
    }
}
