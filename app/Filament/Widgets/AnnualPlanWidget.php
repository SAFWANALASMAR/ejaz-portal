<?php

namespace App\Filament\Widgets;

use App\Models\AnnualPlanItem;
use App\Support\AnnualPlanProgress;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

/**
 * إنجاز الخطة السنوية للسنة الجارية (requirements/13 §3) لمن يطّلع على الخطة: البنود المنفذة ونسبتها،
 * والمستفيدون الفعليون مقابل المستهدف، ثم نسبة كل إدارة وكل نوع. ويظهر في رئيسية اللوحة وفوق شاشة الخطة.
 */
class AnnualPlanWidget extends StatsOverviewWidget
{
    protected static ?int $sort = 3;

    private const ICON = 'heroicon-o-chart-pie';

    public static function canView(): bool
    {
        return auth()->user()?->can('viewAny', AnnualPlanItem::class) ?? false;
    }

    protected function getHeading(): string
    {
        return __('registers.plan.widget_heading', ['year' => (int) now(config('app.display_timezone'))->year]);
    }

    /**
     * العنوان بمربع أيقونة بدل الشريط (البند N2 من تدقيق القبول، سمة اللوحة).
     */
    public function getSectionContentComponent(): Component
    {
        $section = parent::getSectionContentComponent();

        return $section instanceof Section ? $section->icon(self::ICON) : $section;
    }

    /**
     * @return list<Stat>
     */
    protected function getStats(): array
    {
        $progress = AnnualPlanProgress::for((int) now(config('app.display_timezone'))->year);
        $overall = $progress['overall'];

        if ($overall['items'] === 0) {
            return [Stat::make(__('registers.plan.executed_items'), '—')->description(__('registers.plan.empty'))->icon(self::ICON)];
        }

        $stats = [
            Stat::make(__('registers.plan.executed_items'), __('registers.plan.percent', ['value' => $overall['percent']]))
                ->description(__('registers.plan.executed_of', ['executed' => number_format($overall['executed']), 'items' => number_format($overall['items']), 'percent' => $overall['percent']])),
            Stat::make(__('registers.plan.beneficiaries'), number_format($overall['actual']))
                ->description(__('registers.plan.beneficiaries_of', ['actual' => number_format($overall['actual']), 'target' => number_format($overall['target'])])),
        ];

        // الإنجاز لكل إدارة ثم لكل نوع
        foreach (['departments' => 'by_department', 'types' => 'by_type'] as $group => $label) {
            foreach ($progress[$group] as $name => $row) {
                $stats[] = Stat::make(__('registers.plan.'.$label, ['name' => $name]), __('registers.plan.percent', ['value' => $row['percent']]))
                    ->description(__('registers.plan.executed_of', ['executed' => number_format($row['executed']), 'items' => number_format($row['items']), 'percent' => $row['percent']]));
            }
        }

        // كل بطاقة بمربع أيقونة كبقية بطاقات الإحصاء في الرئيسية (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢)
        return array_map(fn (Stat $stat): Stat => $stat->icon(self::ICON), $stats);
    }
}
