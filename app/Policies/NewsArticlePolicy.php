<?php

namespace App\Policies;

/**
 * الأخبار (صلاحيات news.*).
 */
class NewsArticlePolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'news';
    }
}
