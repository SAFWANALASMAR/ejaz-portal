<?php

namespace App\Policies;

class RedirectPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'redirects';
    }
}
