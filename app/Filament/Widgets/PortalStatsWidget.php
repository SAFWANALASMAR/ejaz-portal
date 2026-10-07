<?php

namespace App\Filament\Widgets;

use App\Support\Dates;
use App\Support\PortalStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

/**
 * إحصائيات البوابة في رئيسية اللوحة لمن له stats.view (عضو المجلس والمشرف العام): من المخزّن الذي
 * يحسبه ejaz:refresh-stats، فلا تُحسب عند كل زيارة.
 */
class PortalStatsWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    public static function canView(): bool
    {
        return auth()->user()?->can('stats.view') ?? false;
    }

    protected function getHeading(): string
    {
        return __('surveys.stats.heading');
    }

    protected function getDescription(): ?string
    {
        $at = PortalStats::computedAt();

        return $at === null ? __('surveys.stats.not_ready') : __('surveys.stats.updated', ['time' => Dates::display(Carbon::parse($at), withTime: true)]);
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $values = PortalStats::cached() ?? [];

        return collect(['users', 'verified_users', 'new_users', 'competitions', 'submissions', 'drafts', 'surveys', 'responses'])
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): Stat => Stat::make(__('surveys.stats.'.$key), number_format($values[$key])))
            ->values()
            ->all();
    }
}
