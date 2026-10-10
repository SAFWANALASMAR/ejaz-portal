<?php

namespace App\Policies;

/**
 * أعضاء مجلس الإدارة في صفحة «عن الجمعية» (صلاحيات board.*).
 */
class BoardMemberPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'board';
    }
}
