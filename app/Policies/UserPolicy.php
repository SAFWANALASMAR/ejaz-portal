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
 * - وغير المشرف العام لا يعدّل حساباً له دور إداري، إلا المدير التنفيذي في حسابات الموظفين.
 * ومنح الأدوار قاعدة في الشيفرة لا في ملف الصلاحيات (RoleName::assignableBy): المشرف العام كلها،
 * والمدير التنفيذي أدوار الموظفين («إدارة الموظفين» — requirements/12 §12).
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

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
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
            && ($this->allows($user, 'create') || $this->allows($user, 'update'))
            && ! $this->protectsAdministrator($user, $record);
    }

    /**
     * إخفاء البيانات (معالجة طلب الحذف). وشروط الحساب نفسه في AnonymizeUser::eligible.
     */
    public function anonymize(User $user, User $record): bool
    {
        return $this->allows($user, 'delete') && AnonymizeUser::eligible($user, $record);
    }

    /**
     * حساب له دور إداري لا يعدّله إلا المشرف العام. والمدير التنفيذي يصل إلى حسابات الموظفين وحدها
     * (والقائمة محصورة مثلها في UserResource): أدوارها الإدارية كلها مما يمنحه، أو حساب أنشأه للتو ولم يأخذ دوره بعد.
     * فلا يصل إلى حساب مستفيد، ولا إلى حسابه، ولا إلى المشرف العام أو المجلس أو مدير تنفيذي آخر.
     */
    private function protectsAdministrator(User $user, Model $record): bool
    {
        if (! $record instanceof User || $user->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        $administrative = array_values(array_intersect($record->getRoleNames()->all(), RoleName::values(RoleName::panelRoles())));

        if ($user->hasRole(RoleName::ExecutiveDirector->value)) {
            return ! $record->wasRecentlyCreated
                && ($administrative === [] || array_diff($administrative, RoleName::values(RoleName::assignableBy($user))) !== []);
        }

        return $administrative !== [];
    }
}
