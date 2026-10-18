<?php

namespace App\Filament\Widgets;

use App\Support\Dates;
use App\Support\PortalStats;
use Illuminate\Support\Carbon;

/**
 * المستخدمون والزيارات، وتحت العنوان وقت آخر حساب. وتظهر قبل أول حساب لتقول إنه قريب.
 */
class PeopleStatsWidget extends PortalStatsWidget
{
    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return auth()->user()?->can('stats.view') ?? false;
    }

    protected function group(): string
    {
        return 'people';
    }

    protected function keys(): array
    {
        return ['users', 'verified_users', 'new_users', 'views_today', 'visitors_today'];
    }

    protected function getDescription(): string
    {
        $at = PortalStats::computedAt();

        return $at === null ? __('surveys.stats.not_ready') : __('surveys.stats.updated', ['time' => Dates::display(Carbon::parse($at), withTime: true)]);
    }
}
