<?php

namespace App\Filament\Pages;

use App\Enums\RoleName;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\View\View;

/**
 * رئيسية اللوحة لكل الأدوار (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢): رأس بتحية باسم صاحب الحساب وأدواره وتاريخ اليوم
 * بدل العنوان المجرد وعنصر الحساب، وحالة فارغة مرسومة لمن لا عنصر في رئيسيته. العناصر نفسها وصلاحياتها كما هي.
 */
class Dashboard extends BaseDashboard
{
    public function getHeader(): ?View
    {
        /** @var User $user */
        $user = auth()->user();

        $roles = $user->getRoleNames()
            ->map(fn (string $role): ?string => RoleName::tryFrom($role)?->getLabel())
            ->filter()
            ->values()
            ->all();

        return view('filament.admin.dashboard-hero', [
            'name' => $user->name,
            'roles' => $roles,
            'today' => now(config('app.display_timezone'))->locale('ar')->translatedFormat('l j F Y'),
            'empty' => $this->getVisibleWidgets() === [],
        ]);
    }
}
