<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * عناصر لوحة المستخدم من config/account.php، مصفّاة بالصلاحية وبوجود المسار.
 */
final class AccountNavigation
{
    /**
     * @return Collection<int, array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string}>
     */
    public static function for(User $user): Collection
    {
        /** @var list<array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string}> $links */
        $links = config('account.navigation', []);

        return collect($links)
            ->filter(fn (array $link): bool => Route::has($link['route']))
            ->filter(fn (array $link): bool => ! isset($link['can']) || $user->can($link['can']))
            ->values();
    }

    /**
     * @param  array{active: list<string>}  $link
     */
    public static function isActive(array $link): bool
    {
        return request()->routeIs(...$link['active']);
    }
}
