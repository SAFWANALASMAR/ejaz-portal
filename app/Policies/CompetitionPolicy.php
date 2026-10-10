<?php

namespace App\Policies;

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
}
