<?php

namespace App\Support;

use App\Enums\RoleName;
use App\Models\User;

/**
 * من يصل إلى منطقة الجمعية العمومية في الموقع (requirements/04): عضوها (والدور يتبع عضويته الفعّالة)،
 * وعضو مجلس الإدارة، والسكرتير، والمشرف العام. ومجلس الإدارة وحده (بالسكرتير والمشرف العام) يرى
 * ملفات المجلس.
 */
final class AssemblyArea
{
    /**
     * مدة رابط التنزيل الموقّع في المنطقة بالدقائق. والدخول والصلاحية يُفحصان عند الطلب أيضاً.
     */
    public const LINK_MINUTES = 10;

    public static function allows(User $user): bool
    {
        return $user->is_active
            && $user->anonymized_at === null
            && $user->hasAnyRole([RoleName::AssemblyMember->value, ...self::boardRoles()]);
    }

    public static function board(User $user): bool
    {
        return self::allows($user) && $user->hasAnyRole(self::boardRoles());
    }

    /**
     * @return list<string>
     */
    private static function boardRoles(): array
    {
        // والمشرف العام ومديرو النظام (RoleName::overseers — 25 أكتوبر 2026)
        return [RoleName::BoardMember->value, RoleName::Secretary->value, ...RoleName::values(RoleName::overseers())];
    }
}
