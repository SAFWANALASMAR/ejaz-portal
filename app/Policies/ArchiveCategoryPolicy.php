<?php

namespace App\Policies;

use App\Models\ArchiveCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * تصنيفات الأرشيف (صلاحيات archive_categories.*، لا تُربط بدور: المشرف العام وحده). والتصنيف الذي فيه
 * عناصر لا يُحذف.
 */
class ArchiveCategoryPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'archive_categories';
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var ArchiveCategory $record */
        // جدول التصنيفات يحمّل وجود العناصر مسبقاً (withExists)، فلا استعلام لكل صف
        $hasItems = $record->hasAttribute('has_any_items') ? (bool) $record->getAttribute('has_any_items') : $record->items()->withTrashed()->exists();

        return ! $hasItems && $this->allows($user, 'delete');
    }
}
