<?php

namespace App\Filament\Widgets;

use App\Support\PortalStats;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * مجموعة من إحصائيات البوابة في رئيسية اللوحة لمن له stats.view (عضو المجلس والمدير التنفيذي والمشرف العام):
 * من المخزّن الذي يحسبه ejaz:refresh-stats كل عشر دقائق، فلا تُحسب عند كل زيارة ولا يُستطلع لها، وتُعرض مع
 * الصفحة لا بطلب مؤجل لأنها قراءة واحدة من الذاكرة المؤقتة.
 * ولا تظهر المجموعة قبل أول حساب إلا الأولى، وفيها أنه قريب (PeopleStatsWidget).
 */
abstract class PortalStatsWidget extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected ?string $pollingInterval = null;

    /**
     * اسم المجموعة في surveys.stats.groups.
     */
    abstract protected function group(): string;

    /**
     * مفاتيح PortalStats بترتيب عرضها.
     *
     * @return list<string>
     */
    abstract protected function keys(): array;

    public static function canView(): bool
    {
        return (auth()->user()?->can('stats.view') ?? false) && PortalStats::cached() !== null;
    }

    protected function getHeading(): string
    {
        return __('surveys.stats.groups.'.$this->group());
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $values = PortalStats::cached() ?? [];

        return collect($this->keys())
            ->filter(fn (string $key): bool => array_key_exists($key, $values))
            ->map(fn (string $key): Stat => Stat::make(__('surveys.stats.'.$key), number_format($values[$key]))->icon($this->icon()))
            ->values()
            ->all();
    }

    /**
     * أيقونة المجموعة، تظهر في مربع متدرج بسمة اللوحة (resources/css/filament/admin/theme.css).
     */
    protected function icon(): string
    {
        return match ($this->group()) {
            'people' => 'heroicon-o-users',
            'requests' => 'heroicon-o-clipboard-document-list',
            'programs' => 'heroicon-o-academic-cap',
            'assembly' => 'heroicon-o-building-library',
            default => 'heroicon-o-chart-bar',
        };
    }
}
