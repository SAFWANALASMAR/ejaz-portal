<?php

namespace App\Support;

use App\Enums\AccountCardAudience;
use App\Enums\RoleName;
use App\Models\AccountCard;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

/**
 * عناصر لوحة المستخدم من config/account.php، مصفّاة بالصلاحية وبوجود المسار.
 */
final class AccountNavigation
{
    /**
     * @return Collection<int, array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string, enabled?: string, deferred?: bool}>
     */
    public static function for(User $user): Collection
    {
        /** @var list<array{route: string, label: string, icon: string, active: list<string>, description?: string, can?: string, enabled?: string, deferred?: bool}> $links */
        $links = config('account.navigation', []);

        return collect($links)
            ->filter(fn (array $link): bool => Route::has($link['route']))
            // وحدة مؤرشفة خلف مفتاح (مثل الدفع) لا تظهر ما دام معطّلاً
            ->filter(fn (array $link): bool => ! isset($link['enabled']) || (bool) config($link['enabled']))
            // والمؤجلة بقرار المدير التنفيذي لا تظهر حتى تُظهر من اللوحة، إلا للمشرف العام والمسؤول التقني (صفحة المشرف العام
            // فيها «الجمعية العمومية» — ملاحظات المدير التنفيذي، 25 أكتوبر 2026)
            ->filter(fn (array $link): bool => ! ($link['deferred'] ?? false) || AccountSettings::showsDeferredPortals() || RoleName::isOverseer($user))
            ->filter(fn (array $link): bool => ! isset($link['can']) || $user->can($link['can']))
            ->values();
    }

    /**
     * بطاقات الرئيسية من اللوحة (AccountCard — ملاحظات المدير التنفيذي، 25 أكتوبر 2026) بترتيبها: الظاهرة، لمن تظهر له،
     * وبطاقة القسم المبني لمن يظهر له قسمه في القائمة (الصلاحية والمفتاح والتأجيل أعلاه).
     *
     * @return Collection<int, array{route: string|null, href: string, title: string, description: string|null, icon: string, external: bool}>
     */
    public static function cards(User $user): Collection
    {
        $sections = self::for($user)->pluck('route')->all();
        $panel = $user->canAccessPanel(filament()->getPanel('admin'));

        /** @var Collection<int, array{route: string|null, href: string, title: string, description: string|null, icon: string, external: bool}> $cards */
        $cards = AccountCard::query()
            ->where('is_visible', true)
            ->orderBy('sort')
            ->orderBy('id')
            ->get()
            ->filter(fn (AccountCard $card): bool => $card->route === null || in_array($card->route, $sections, true))
            ->filter(fn (AccountCard $card): bool => match ($card->audience) {
                AccountCardAudience::Panel => $panel,
                // أصحاب الأدوار المختارة وحدهم (مراجعة المشرف، 26 أكتوبر 2026)
                AccountCardAudience::Roles => $user->hasAnyRole(array_values(array_filter((array) $card->roles, 'is_string'))),
                default => true,
            })
            ->map(fn (AccountCard $card): ?array => ($href = $card->href()) === null ? null : [
                'route' => $card->route,
                'href' => $href,
                'title' => $card->title,
                'description' => $card->description,
                'icon' => $card->icon->value,
                // لوحة الإدارة وما خارج البوابة تحميل كامل (SpaLink)، وما خارجها في تبويب جديد
                'external' => ! str_starts_with($href, '/') && ! str_starts_with($href, (string) config('app.url')),
            ])
            ->filter()
            ->values();

        return $cards;
    }

    /**
     * @param  array{active: list<string>}  $link
     */
    public static function isActive(array $link): bool
    {
        return request()->routeIs(...$link['active']);
    }
}
