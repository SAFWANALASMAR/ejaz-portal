<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;

/**
 * نموذج له حالة نشر (`is_published`): نطاق `published()` يعيد المنشور وحده. والنموذج الذي يرتّب مع
 * النشر (المبادرات، والشركاء) يُبقي نطاقه.
 */
trait Publishable
{
    /**
     * @param  Builder<static>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('is_published', true);
    }
}
