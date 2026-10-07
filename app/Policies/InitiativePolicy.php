<?php

namespace App\Policies;

class InitiativePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'initiatives';
    }
}
