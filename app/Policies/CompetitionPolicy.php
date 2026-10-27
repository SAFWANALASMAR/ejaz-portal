<?php

namespace App\Policies;

use App\Models\Competition;
use App\Models\User;
use App\Support\CompetitionAccess;
use App\Support\NationalIdExport;

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
     * رقم هوية الطالب وجوال ولي أمره كاملين: لمن يملك التصدير الكامل (NationalIdExport — مديرا النظام ومن يمنحانه الخيار)
     * فيما يدير نتائجه، لا لمنشئ المسابقة تلقائياً (قرار صفوان، 27 أكتوبر 2026). والفحص بالربط نفسه (checkPermissionTo)
     * لا بـ can، فلا يناله المشرف العام ضمناً عبر Gate::after، والجواب صريح فلا يغيّره.
     */
    public function viewNationalId(User $user, Competition $competition): bool
    {
        return NationalIdExport::allows($user) && CompetitionAccess::managesResults($user, $competition);
    }
}
