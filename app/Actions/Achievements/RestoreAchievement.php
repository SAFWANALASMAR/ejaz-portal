<?php

namespace App\Actions\Achievements;

use App\Models\Achievement;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

/**
 * استعادة إنجاز محذوف حذفاً ناعماً بحالته ومرفقه (AchievementPolicy::restore)، وتُسجَّل الاستعادة (LogsActivity).
 * شاشة اللوحة تستدعي هذا الإجراء لا الاستعادة مباشرة (مراجعة المشرف المنخفضة 1 على #122).
 */
class RestoreAchievement
{
    /**
     * @throws AuthorizationException
     */
    public function handle(User $actor, Achievement $achievement): void
    {
        if (! $actor->can('restore', $achievement)) {
            throw new AuthorizationException;
        }

        $achievement->restore();
    }
}
