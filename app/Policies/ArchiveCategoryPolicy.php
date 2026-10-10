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
        return ! $record->items()->withTrashed()->exists() && $this->allows($user, 'delete');
    }
}
