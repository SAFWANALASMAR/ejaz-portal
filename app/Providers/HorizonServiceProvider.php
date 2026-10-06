<?php

namespace App\Providers;

use App\Enums\RoleName;
use App\Models\User;
use Laravel\Horizon\Horizon;
use Laravel\Horizon\HorizonApplicationServiceProvider;

class HorizonServiceProvider extends HorizonApplicationServiceProvider
{
    /**
     * الأصل في Horizon أنه مفتوح للجميع في البيئة المحلية.
     * هنا يُطبَّق الشرط في كل البيئات: المشرف العام بتحقق ثنائي مفعّل.
     * الشرط مكتوب هنا صراحة لا عبر Gate، حتى لا يكفي الدور وحده دون التحقق الثنائي.
     */
    protected function authorization(): void
    {
        Horizon::auth(function ($request): bool {
            $user = $request->user();

            return $user instanceof User
                && $user->hasRole(RoleName::SuperAdmin->value)
                && $user->hasEnabledTwoFactorAuthentication();
        });
    }
}
