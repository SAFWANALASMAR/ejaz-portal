<?php

namespace App\Policies;

/**
 * وثائق الحوكمة (صلاحيات governance.*). الحذف ناعم ويُستعاد، والحذف النهائي ممنوع على
 * الجميع (ModulePolicy).
 */
class DocumentPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'governance';
    }
}
