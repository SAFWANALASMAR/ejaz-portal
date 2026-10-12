<?php

namespace App\Policies;

/**
 * الخطة السنوية للبرامج (annual_plan.*): يديرها المدير التنفيذي ومدير المشاريع، ويطّلع عليها مجلس الإدارة.
 */
class AnnualPlanItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'annual_plan';
    }
}
