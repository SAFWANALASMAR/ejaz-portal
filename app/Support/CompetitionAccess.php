<?php

namespace App\Support;

use App\Models\Competition;
use App\Models\User;

/**
 * صلاحيات الكتابة في مسابقة الطلاب بالربط نفسه (checkPermissionTo) لا بـ can: المشرف العام اطلاع فقط (قرار المدير
 * التنفيذي)، و`Gate::after` يمنحه كل صلاحية لم تُمنع صراحة (مراجعة المشرف).
 */
final class CompetitionAccess
{
    public static function allows(?User $user, string $permission): bool
    {
        return $user !== null && $user->is_active && $user->checkPermissionTo($permission);
    }

    /**
     * إدارة نتائج مسابقة الطلاب (النشر، والسحب، والتأهيل، والشهادات، وتصدير النتائج): للمدير التنفيذي والمسؤول
     * التقني (competition.manage_results)، ولمنشئ المسابقة ما دام يدير المسابقات. لا لكل من يدير المسابقات كمسؤول
     * البرامج (مراجعة المطابقة).
     */
    public static function managesResults(?User $user, Competition $competition): bool
    {
        return self::allows($user, 'competition.manage_results')
            || ($user !== null && $competition->created_by !== null && $competition->created_by === $user->id && self::allows($user, 'competitions.update'));
    }
}
