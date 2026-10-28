<?php

namespace App\Actions\Users;

use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\NationalIdExport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

/**
 * منح تصدير رقم الهوية كاملاً لمستخدم مباشرة لا عبر دور، أو سحبه (قرار صفوان، 27 أكتوبر 2026): لمديري النظام وحدهم،
 * فيمنحه المدير من يختار دون دور إداري كامل. ولا يُمنح للمشرف العام (اطلاع فقط)، ولمديري النظام هو بدورهم. ويُسجَّل كل
 * منح وسحب بمن نفّذه ولمن ومتى، ويُسحب بتعطيل الحساب أو منحه دور المشرف العام (drop). والتصدير الكامل بعده مشروط
 * بكلمة المرور وسجله (NationalIdExport::authorizeFull)، وبه وحده كشف الرقم في اللوحة (CompetitionPolicy::viewNationalId).
 */
class ChangeNationalIdExport
{
    /**
     * @throws AuthorizationException
     * @throws ValidationException منحه للمشرف العام أو لحساب معطّل
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

        // ولا لحساب معطّل، فلا يعود صامتاً بإعادة تفعيله (مراجعة المشرف)
        if ($granted && ! $user->is_active) {
            throw ValidationException::withMessages(['export_national_id' => __('users.national_id_export.not_for_inactive')]);
        }

        // ولا لمن لا يدير نتائج المسابقات ولا المسابقات نفسها (منشئاً)، فلا يُمنح خيار لا يصل به إلى نتائج فيبقى صامتاً حتى
        // يُمنح دوراً لاحقاً (المراجعة المستقلة على v0.14.0)
        if ($granted && ! CompetitionAccess::allows($user, 'competition.manage_results') && ! CompetitionAccess::allows($user, 'competitions.update')) {
            throw ValidationException::withMessages(['export_national_id' => __('users.national_id_export.not_for_non_manager')]);
        }

        $granted
            ? $user->givePermissionTo(NationalIdExport::PERMISSION)
            : $user->revokePermissionTo(NationalIdExport::PERMISSION);

        self::log($actor, $user, $granted ? ActivityEvent::NationalIdExportGranted : ActivityEvent::NationalIdExportRevoked);
    }

    /**
     * سحب المنح المباشر بسبب لا بقرار: تعطيل الحساب، أو منحه دور المشرف العام. ويُسجَّل بسببه، ولا شيء إن لم يكن ممنوحاً.
     */
    public static function drop(?User $actor, User $user, string $reason): void
    {
        if (! $user->hasDirectPermission(NationalIdExport::PERMISSION)) {
            return;
        }

        $user->revokePermissionTo(NationalIdExport::PERMISSION);
        self::log($actor, $user, ActivityEvent::NationalIdExportRevoked, ['reason' => $reason]);
    }

    /**
     * @param  array<string, string>  $properties
     */
    private static function log(?User $actor, User $user, ActivityEvent $event, array $properties = []): void
    {
        activity('users')->causedBy($actor)->performedOn($user)
            ->event($event->value)
            ->withProperties($properties)
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
