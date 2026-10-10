<?php

namespace App\Policies;

/**
 * الشركاء (صلاحيات partners.*).
 */
class PartnerPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'partners';
    }
}
