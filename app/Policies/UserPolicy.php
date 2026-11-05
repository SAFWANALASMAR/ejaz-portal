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
     * تحرير رقم جوال غير موثق من حساب حجزه عن صاحبه (ReleasePhone — decisions/2026-11-05-phone-login.md): لمديري النظام
     * وحدهما، فيما يعدّلانه من الحسابات. ولا لرقم موثق، فصاحبه أثبته بالرمز.
     */
    public function releasePhone(User $user, User $record): bool
    {
        return RoleName::isAdministrator($user)
            && $record->phone !== null
            && $record->phone_verified_at === null
            && $this->update($user, $record);
    }

    /**
     * كلمة مرور جديدة من اللوحة لمن لا يستعيدها بنفسه: لا بريد موثق له ولا جوال موثق (ملاحظات المدير التنفيذي، 25 أكتوبر
     * 2026). للمدير التنفيذي والمسؤول التقني (accounts.update)، فيما يعدّله من الحسابات، ولا لحسابه نفسه.
     */
    public function resetPassword(User $user, User $record): bool
    {
        return $user->isNot($record)
            // ولا لحساب رقيب (المشرف العام ومديرو النظام): يستعيد كلمة مروره ببريده الموثق (مراجعة المشرف، 26 أكتوبر 2026)
            && ! RoleName::isOverseer($record)
            && $record->anonymized_at === null
            && ! $record->hasVerifiedEmail()
            && ! $record->hasVerifiedPhone()
            && $user->can('accounts.update')
            && $this->update($user, $record);
    }

    /**
     * إعادة ضبط التحقق الثنائي لمن فقد تطبيقه (قرار صفوان، 3 نوفمبر 2026 — docs/decisions/2026-11-03-2fa-admin-reset.md):
     * بصلاحية users.reset_two_factor (لمديري النظام)، لحساب فعّل التحقق الثنائي، لا لحسابه نفسه، ولا للمشرف العام (اطلاع
     * فقط) ولو وصلته الصلاحية. ولا يعيد الضبط لمن هو أعلى منه أو في رتبته، بلا استثناء: فلا يعيده مدير نظام لمدير نظام
     * آخر، ويُعاد لمدير النظام بأمر الطرفية على الخادم (ejaz:reset-two-factor — مواصفة صفوان، قرار المشرف 3 نوفمبر 2026).
     * والنتيجة صريحة فلا يغيّرها Gate::after.
     */
    public function resetTwoFactor(User $user, User $record): bool
    {
        return $user->isNot($record)
            && $record->anonymized_at === null
            && $record->hasEnabledTwoFactorAuthentication()
            && $this->allows($user, 'reset_two_factor')
            && (! $user->hasRole(RoleName::SuperAdmin->value) || RoleName::isAdministrator($user))
            && self::rank($user) > self::rank($record);
    }

    /**
     * رتبة الحساب في إعادة الضبط: مديرو النظام أعلاها، ثم كل دور يدخل اللوحة (ومنه المشرف العام)، ثم من سواهم.
     */
    private static function rank(User $user): int
    {
        return match (true) {
            RoleName::isAdministrator($user) => 2,
            $user->hasAnyRole(RoleName::values(RoleName::panelRoles())) => 1,
            default => 0,
        };
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
