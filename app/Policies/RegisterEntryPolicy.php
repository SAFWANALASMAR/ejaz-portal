<?php

namespace App\Policies;

/**
 * السجلات والمستندات ذات الصلاحية (registers.*): يديرها المدير التنفيذي والسكرتير، ويطّلع عليها مجلس الإدارة. والتنزيل لمن يطّلع.
 */
class RegisterEntryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'registers';
    }
}
