<?php

namespace App\Policies;

use App\Models\PageRevision;
use App\Models\User;

/**
 * نسخ صفحات الموقع الثابتة في «محرر الصفحات»: كل عملية لمن له pages.edit_all وحده (المسؤول التقني والمدير التنفيذي — قرار
 * صفوان، 31 أكتوبر 2026). والنتيجة صريحة، فلا يمنحها Gate::after للمشرف العام اطلاعاً. ولا تعديل لنسخة ولا حذف: المسودة
 * تُكتب بإجرائها، والرجوع نسخة جديدة.
 */
class PageRevisionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('pages.edit_all');
    }

    public function view(User $user, PageRevision $revision): bool
    {
        return $user->can('pages.edit_all');
    }

    public function preview(User $user, PageRevision $revision): bool
    {
        return $user->can('pages.edit_all');
    }

    public function revert(User $user, PageRevision $revision): bool
    {
        return $user->can('pages.edit_all');
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, PageRevision $revision): bool
    {
        return false;
    }

    public function delete(User $user, PageRevision $revision): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
