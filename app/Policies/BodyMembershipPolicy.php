<?php

namespace App\Policies;

/**
 * عضوية المجلس واللجان (committees.*): يديرها السكرتير، ويطّلع عليها المدير التنفيذي ومجلس الإدارة.
 */
class BodyMembershipPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'committees';
    }
}
