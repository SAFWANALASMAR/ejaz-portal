<?php

namespace App\Support;

use App\Auth\CurrentPasswordLimiter;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * تصدير نتائج مسابقة الطلاب برقم الهوية وجوال ولي الأمر كاملين (ملاحظة الاختبار الشامل 10، 26 أكتوبر 2026): التصدير
 * الافتراضي مقنّع (آخر أربعة أرقام)، والكامل خيار منفصل بصلاحية مستقلة لا تُربط إلا بمديري النظام، وبكلمة المرور الحالية
 * في كل مرة (قفلها مشترك مع تأكيد كلمة المرور — CurrentPasswordLimiter). والفحص بالربط نفسه (CompetitionAccess)، فلا
 * يناله المشرف العام ضمناً ولا منشئ المسابقة.
 */
final class NationalIdExport
{
    public const PERMISSION = 'competition.export_national_id';

    public static function allows(?User $user): bool
    {
        return CompetitionAccess::allows($user, self::PERMISSION);
    }

    /**
     * @throws AuthorizationException
     * @throws ValidationException كلمة مرور خاطئة، أو محاولات كثيرة
     */
    public static function authorizeFull(User $actor, ?string $password): void
    {
        if (! self::allows($actor)) {
            throw new AuthorizationException(__('competitions.student.admin.export_full_not_allowed'));
        }

        CurrentPasswordLimiter::ensureNotLocked($actor, 'password');

        if (! Hash::check((string) $password, $actor->password)) {
            CurrentPasswordLimiter::failed($actor);

            throw ValidationException::withMessages(['password' => __('auth.password')]);
        }

        CurrentPasswordLimiter::passed($actor);
    }
}
