<?php

namespace App\Filament\Widgets;

use App\Enums\ServiceRequestStatus;

/**
 * الطلبات الإلكترونية المرسلة، ثم كل حالة بعددها. بلا المسودات، فلا يراها غير أصحابها.
 */
class RequestsStatsWidget extends PortalStatsWidget
{
    protected static ?int $sort = 6;

    protected function group(): string
    {
        return 'requests';
    }

    protected function keys(): array
    {
        return ['requests', ...collect(ServiceRequestStatus::cases())
            ->reject(fn (ServiceRequestStatus $status): bool => $status === ServiceRequestStatus::Draft)
            ->map(fn (ServiceRequestStatus $status): string => 'requests_'.$status->value)
            ->values()
            ->all()];
    }
}
