<?php

namespace App\Policies;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * «إنجازاتنا» (صلاحيات achievements.*): تضيفها وتعدّلها وتحذفها الإدارة التنفيذية وحدها (السكرتير والمدير التنفيذي والمسؤول
 * التقني — قرار صفوان 31 أكتوبر 16:28). والمجلس والجمعية العمومية يطّلعون على المنشور في بوابتهم، والزائر في الصفحة العامة.
 * ومرفق المنشور لكل من معه رابطه الموقّع، ومرفق المسودة لمن يطّلع عليها في اللوحة وحده.
 */
class AchievementPolicy extends ModulePolicy
{
    protected function module(): string
    {
        return 'achievements';
    }

    /**
     * المحذوف حذفاً ناعماً يُستعاد أولاً ثم يُعدَّل، فلا يُنشر وهو محذوف.
     */
    public function update(User $user, Model $record): bool
    {
        return ! ($record instanceof Achievement && $record->trashed()) && parent::update($user, $record);
    }

    public function viewAttachment(?User $user, Achievement $achievement): bool
    {
        if ($achievement->isPublished()) {
            return true;
        }

        return $user !== null && $user->is_active && $this->allows($user, 'view');
    }
}
