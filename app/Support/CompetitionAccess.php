<?php

namespace App\Support;

use App\Models\User;

/**
 * صلاحيات الكتابة في مسابقة الطلاب بالربط نفسه (checkPermissionTo) لا بـ can: المشرف العام اطلاع فقط (قرار المدير
 * التنفيذي)، و`Gate::after` يمنحه كل صلاحية لم تُمنع صراحة. فالنشر والسحب والتأهيل والشهادات والتصدير وحفظ
 * المراحل والأسئلة والنصوص والحقول لمن رُبطت به الصلاحية وحده (مراجعة المشرف).
 */
final class CompetitionAccess
{
    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null && $user->is_active && $user->checkPermissionTo($permission);
    }
}
