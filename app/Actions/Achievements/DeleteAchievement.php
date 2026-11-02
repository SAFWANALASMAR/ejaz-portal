<?php

namespace App\Actions\Achievements;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * حذف إنجاز حذفاً ناعماً (AchievementPolicy::delete — الإدارة التنفيذية وحدها). يبقى مرفقه في القرص ليُستعاد معه،
 * ويُسجَّل الحذف (LogsActivity). شاشة اللوحة تستدعي هذا الإجراء لا الحذف مباشرة (مراجعة المشرف المنخفضة 1 على #122).
 */
class DeleteAchievement
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Achievement $achievement): void
    {
        if (! $actor->can('delete', $achievement)) {
            throw new AuthorizationException;
        }

        $achievement->delete();
    }
}
