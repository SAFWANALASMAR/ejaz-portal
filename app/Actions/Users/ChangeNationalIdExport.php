<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\NationalIdExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * منح تصدير رقم الهوية كاملاً لمستخدم مباشرة لا عبر دور، أو سحبه (قرار صفوان، 27 أكتوبر 2026): لمديري النظام وحدهم،
 * فيمنحه المدير من يختار دون دور إداري كامل. ولا يُمنح للمشرف العام (اطلاع فقط)، ولمديري النظام هو بدورهم. ويُسجَّل كل
 * منح وسحب بمن نفّذه ولمن ومتى. والتصدير الكامل بعده مشروط بكلمة المرور وسجله (NationalIdExport::authorizeFull).
 */
class ChangeNationalIdExport
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException منحه للمشرف العام
     */
    public function handle(User $actor, User $user, bool $granted): void
    {
        if (! self::manages($actor) || $user->anonymized_at !== null) {
            throw new AuthorizationException;
        }

        // ومن صار مدير نظام في الحفظ نفسه فهو له بدوره
        if (RoleName::isAdministrator($user) || $granted === $user->hasDirectPermission(NationalIdExport::PERMISSION)) {
            return;
        }

        if ($granted && $user->hasRole(RoleName::SuperAdmin->value)) {
            throw ValidationException::withMessages(['export_national_id' => __('users.national_id_export.not_for_super_admin')]);
        }

        $granted
            ? $user->givePermissionTo(NationalIdExport::PERMISSION)
            : $user->revokePermissionTo(NationalIdExport::PERMISSION);

        $event = $granted ? ActivityEvent::NationalIdExportGranted : ActivityEvent::NationalIdExportRevoked;

        activity('users')->causedBy($actor)->performedOn($user)
            ->event($event->value)
            ->log($event->value);
    }

    /**
     * من يرى الخيار ويغيّره: المدير التنفيذي والمسؤول التقني.
     */
    public static function manages(?User $actor): bool
    {
        return $actor !== null && $actor->is_active && RoleName::isAdministrator($actor);
    }
}
