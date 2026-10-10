<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Support\Portals;
use Filament\Widgets\Widget;

/**
 * «بوابتي» في رئيسية اللوحة: لكل فئة ينتمي إليها الحساب (requirements/12 §12) قدراتها بنصوص المتطلبات،
 * وكل قدرة رابط إلى شاشتها إن كان يملك فتحها (Portals).
 */
class PortalWidget extends Widget
{
    protected static ?int $sort = 0;

    protected string $view = 'filament.widgets.portal-widget';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user instanceof User && Portals::for($user) !== [];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        /** @var User $user */
        $user = auth()->user();

        return ['portals' => Portals::for($user)];
    }
}
