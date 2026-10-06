<?php

namespace App\Policies;

class MenuItemPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'menu';
    }
}
