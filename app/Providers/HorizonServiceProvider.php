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
     * هنا يُطبَّق الشرط في كل البيئات: مديرو النظام (المدير التنفيذي والمسؤول التقني) بتحقق ثنائي مفعّل (فيه إعادة المهام وحذفها، فلا يصله المشرف العام وهو اطلاع فقط).
     * الشرط مكتوب هنا صراحة لا عبر Gate، حتى لا يكفي الدور وحده دون التحقق الثنائي.
     */
    protected function authorization(): void
    {
        Horizon::auth(function ($request): bool {
            $user = $request->user();

            return $user instanceof User
                && RoleName::isAdministrator($user)
                && $user->hasEnabledTwoFactorAuthentication();
        });
    }
}
