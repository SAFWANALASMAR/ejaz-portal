<?php

namespace App\Policies;

/**
 * «منشوراتنا» (صلاحيات social_posts.*): محرر المحتوى ومديرا النظام.
 */
class SocialPostPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'social_posts';
    }
}
