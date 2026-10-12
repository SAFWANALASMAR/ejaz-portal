<?php

namespace App\Policies;

/**
 * لجان الجمعية (committees.*): يديرها السكرتير، ويطّلع عليها المدير التنفيذي ومجلس الإدارة.
 */
class CommitteePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'committees';
    }
}
