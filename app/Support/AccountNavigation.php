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
     * @return Collection<int, array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string, enabled?: string}>
     */
    public static function for(User $user): Collection
    {
        /** @var list<array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string, enabled?: string}> $links */
        $links = config('account.navigation', []);

        return collect($links)
            ->filter(fn (array $link): bool => Route::has($link['route']))
            // وحدة مؤرشفة خلف مفتاح (مثل الدفع) لا تظهر ما دام معطّلاً
            ->filter(fn (array $link): bool => ! isset($link['enabled']) || (bool) config($link['enabled']))
            ->filter(fn (array $link): bool => ! isset($link['can']) || $user->can($link['can']))
            ->values();
    }

    /**
     * بطاقات الرئيسية: العناصر التي لها وصف، بترتيب account.cards، وما لم يُذكر فيه بعدها بترتيب
     * القائمة.
     *
     * @return Collection<int, array{route: string, label: string, icon: string, active: list<string>, description: string, can?: string}>
     */
    public static function cards(User $user): Collection
    {
        /** @var list<string> $order */
        $order = config('account.cards', []);

        return self::for($user)
            ->filter(fn (array $link): bool => isset($link['description']))
            ->sortBy(fn (array $link, int $index): int => ($position = array_search($link['route'], $order, true)) === false ? count($order) + $index : $position)
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
