<?php

namespace App\Policies;

/**
 * حسابات المنصات بلا كلمات مرور (platform_accounts.*): للمدير التنفيذي والسكرتير وحدهما.
 */
class PlatformAccountPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'platform_accounts';
    }
}
