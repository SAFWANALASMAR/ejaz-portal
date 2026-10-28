<?php

namespace App\Support;

/**
 * صلاحيات الاطلاع وحدها، لدور المشرف العام (اطلاع فقط — المدير التنفيذي عبر صفوان، 25 أكتوبر 2026): يرى كل شيء في
 * اللوحة، ولا يضيف ولا يعدّل ولا يحذف ولا يصدّر ولا يعتمد. والمنع في السياسات (Gate::after في AppServiceProvider)،
 * لا في الواجهة وحدها.
 */
final class ReadOnlyAccess
{
    /**
     * عمليات السياسات التي تقرأ ولا تغيّر شيئاً.
     */
    private const POLICY_ABILITIES = ['viewAny', 'view'];

    /**
     * عمليات ملف الصلاحيات (config/permissions.php) التي تقرأ: <الوحدة>.view وأخواتها.
     */
    private const PERMISSION_ACTIONS = ['view', 'view_all', 'view_donor'];

    public static function allows(string $ability): bool
    {
        if (in_array($ability, self::POLICY_ABILITIES, true)) {
            return true;
        }

        $action = str_contains($ability, '.') ? substr($ability, strrpos($ability, '.') + 1) : null;

        return $action !== null && in_array($action, self::PERMISSION_ACTIONS, true);
    }
}
