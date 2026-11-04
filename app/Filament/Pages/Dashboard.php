<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;

/**
 * رئيسية اللوحة لكل الأدوار (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢): رأس بتحية باسم صاحب الحساب وأدواره وتاريخ اليوم
 * بدل العنوان المجرد وعنصر الحساب، وحالة فارغة مرسومة لمن لا عنصر في رئيسيته. العناصر نفسها وصلاحياتها كما هي.
 */
class Dashboard extends BaseDashboard
{
    /** عدد أزرار المجموعات في الحالة الفارغة */
    private const SHORTCUTS = 4;

    public function getHeader(): ?View
    {
        /** @var User $user */
        $user = auth()->user();

        $roles = $user->getRoleNames()
            ->map(fn (string $role): ?string => RoleName::tryFrom($role)?->getLabel())
            ->filter()
            ->values()
            ->all();

        $empty = $this->getVisibleWidgets() === [];

        return view('filament.admin.dashboard-hero', [
            'name' => $user->name,
            'roles' => $roles,
            'today' => now(config('app.display_timezone'))->locale('ar')->translatedFormat('l j F Y'),
            'empty' => $empty,
            'shortcuts' => $empty ? $this->shortcuts() : [],
        ]);
    }

    /**
     * أول مجموعات القائمة التي يملكها الحساب (ملاحظة المقيّم على #168): لكل مجموعة اسمها وأول شاشة فيها بأيقونتها. والقائمة نفسها
     * مفلترة بصلاحيات الحساب في Filament، فلا زر لشاشة لا يملكها.
     *
     * @return list<array{label: string, url: string, icon: mixed}>
     */
    private function shortcuts(): array
    {
        $shortcuts = [];

        foreach (filament()->getNavigation() as $group) {
            if (blank($group->getLabel())) {
                continue;
            }

            $first = collect($group->getItems())->first(fn (mixed $item): bool => $item instanceof NavigationItem && filled($item->getUrl()));

            if (! $first instanceof NavigationItem) {
                continue;
            }

            $shortcuts[] = ['label' => (string) $group->getLabel(), 'url' => (string) $first->getUrl(), 'icon' => $first->getIcon()];

            if (count($shortcuts) === self::SHORTCUTS) {
                break;
            }
        }

        return $shortcuts;
    }
}
