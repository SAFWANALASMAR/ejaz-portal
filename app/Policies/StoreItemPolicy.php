<?php

namespace App\Policies;

/**
 * عناصر «المتجر والتبرعات» (صلاحيات store_items.*).
 */
class StoreItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'store_items';
    }
}
