<?php

namespace App\Support;

use App\Auth\CurrentPasswordLimiter;
use App\Enums\ActivityEvent;
use App\Enums\RoleName;
use App\Models\Competition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * تصدير نتائج مسابقة الطلاب برقم الهوية وجوال ولي الأمر كاملين (ملاحظة الاختبار الشامل 10، 26 أكتوبر 2026): التصدير
 * الافتراضي مقنّع (آخر أربعة أرقام)، والكامل خيار منفصل بصلاحية مستقلة لمديري النظام بدورهم، ولمن يمنحونه إياها مباشرة
 * (ChangeNationalIdExport — قرار صفوان، 27 أكتوبر 2026)، وبكلمة المرور الحالية
 * في كل مرة (قفلها مشترك مع تأكيد كلمة المرور — CurrentPasswordLimiter). والفحص بالربط نفسه (CompetitionAccess)، فلا
 * يناله المشرف العام ضمناً ولا منشئ المسابقة. والمحاولة المرفوضة تُسجَّل بسببها، فتظهر محاولات جلسة مسروقة في السجل.
 */
final class NationalIdExport
{
    public const PERMISSION = 'competition.export_national_id';

    public static function allows(?User $user): bool
    {
        // والمشرف العام اطلاع فقط ولو وصلته الصلاحية مباشرة قبل دوره
        return $user !== null && CompetitionAccess::allows($user, self::PERMISSION)
            && (! $user->hasRole(RoleName::SuperAdmin->value) || RoleName::isAdministrator($user));
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException كلمة مرور خاطئة، أو محاولات كثيرة
     */
    public static function authorizeFull(User $actor, Competition $competition, ?string $password): void
    {
        if (! self::allows($actor)) {
            self::denied($actor, $competition, 'permission');

            throw new AuthorizationException(__('competitions.student.admin.export_full_not_allowed'));
        }

        try {
            CurrentPasswordLimiter::ensureNotLocked($actor, 'password');
        } catch (ValidationException $exception) {
            self::denied($actor, $competition, 'locked');

            throw $exception;
        }

        if (! Hash::check((string) $password, $actor->password)) {
            CurrentPasswordLimiter::failed($actor);
            self::denied($actor, $competition, 'password');

            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        CurrentPasswordLimiter::passed($actor);
    }

    private static function denied(User $actor, Competition $competition, string $reason): void
    {
        activity('competitions')->performedOn($competition)->causedBy($actor)
            ->event(ActivityEvent::NationalIdExportDenied->value)
            ->withProperties(['reason' => $reason])
            ->log(ActivityEvent::NationalIdExportDenied->value);
    }
}
