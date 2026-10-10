<?php

namespace App\Policies;

/**
 * فرص الخدمات الإلكترونية (صلاحيات openings.*). الحذف ناعم، والحذف النهائي ممنوع (ModulePolicy).
 */
class OpeningPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'openings';
    }
}
