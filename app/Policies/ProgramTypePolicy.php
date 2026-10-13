<?php

namespace App\Policies;

/**
 * أنواع البرامج (صلاحيات program_types.*).
 */
class ProgramTypePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'program_types';
    }
}
