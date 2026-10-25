<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\User;

/**
 * المسابقات في اللوحة (صلاحيات competitions.*): مسؤول البرامج يديرها، وعضو المجلس يطّلع.
 * والحذف ناعم ويُستعاد، والحذف النهائي ممنوع على الجميع (ModulePolicy).
 */
class CompetitionPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'competitions';
    }

    /**
     * رقم هوية الطالب وجوال ولي أمره كاملين (قرار المدير التنفيذي): منشئ المسابقة، ومن رُبطت به الصلاحية
     * المستقلة competition.view_national_id. والفحص بالربط نفسه (checkPermissionTo) لا بـ can، فلا يناله المشرف
     * العام ضمناً عبر Gate::after، والجواب صريح فلا يغيّره.
     */
    public function viewNationalId(User $user, Competition $competition): bool
    {
        if (! $user->is_active) {
            return false;
        }

        return ($competition->created_by !== null && $competition->created_by === $user->id && $user->checkPermissionTo('competitions.update'))
            || $user->checkPermissionTo('competition.view_national_id');
    }
}
