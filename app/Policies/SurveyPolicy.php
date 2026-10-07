<?php

namespace App\Policies;

/**
 * استبيانات قياس الرضا ونتائجها في اللوحة (صلاحيات surveys.*): مسؤول البرامج يديرها، وعضو المجلس
 * يطّلع. والحذف ناعم، والحذف النهائي ممنوع على الجميع (ModulePolicy).
 */
class SurveyPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'surveys';
    }
}
