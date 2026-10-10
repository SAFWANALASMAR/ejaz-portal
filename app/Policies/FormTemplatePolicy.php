<?php

namespace App\Policies;

/**
 * النماذج الإلكترونية (صلاحيات forms.*): يديرها السكرتير ويطّلع عليها المجلس.
 */
class FormTemplatePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'forms';
    }
}
