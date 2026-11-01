<?php

namespace App\Policies;

use App\Models\LibraryItem;
use App\Models\LibraryProgress;
use App\Models\User;

/**
 * «قراءاتي» بيانات شخصية: صاحبها وحده يراها ويكتبها، ولا يراها أحد غيره ولو كان مديراً (للإدارة مجاميعها وحدها —
 * LibraryProgress::totals). ولا شاشة لها في اللوحة.
 */
class LibraryProgressPolicy
{
    public function viewAny(User $user): bool
    {
        return false;
    }

    public function view(User $user, LibraryProgress $progress): bool
    {
        return $progress->user_id === $user->id;
    }

    /**
     * يُسجَّل تقدّم الحساب النشط في عنصر منشور وحده، وهو ما يراه في المكتبة.
     */
    public function create(User $user, LibraryItem $item): bool
    {
        return $user->is_active && $user->anonymized_at === null && $item->is_published && ! $item->trashed();
    }
}
