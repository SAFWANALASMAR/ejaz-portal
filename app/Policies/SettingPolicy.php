<?php

namespace App\Policies;

use App\Models\Setting;
use App\Models\User;

/**
 * إعدادات الموقع تُعدَّل كلها من صفحة واحدة بصلاحية settings.update.
 */
class SettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('settings.update');
    }

    public function update(User $user, ?Setting $setting = null): bool
    {
        return $user->can('settings.update');
    }
}
