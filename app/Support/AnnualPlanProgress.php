<?php

namespace App\Support;

use App\Models\AnnualPlanItem;
use Illuminate\Support\Collection;

/**
 * إنجاز الخطة السنوية (requirements/13 §3): عدد البنود والمنفذ منها ونسبته، والمستفيدون الفعليون مقابل
 * المستهدف (من البنود المنفذة)، للسنة كلها ولكل إدارة ونوع. يُحسب من البرامج المربوطة عند الطلب، فيتغير بتنفيذها.
 */
final class AnnualPlanProgress
{
    /**
     * @return array{overall: array{items: int, executed: int, percent: int, target: int, actual: int}, departments: array<string, array{items: int, executed: int, percent: int, target: int, actual: int}>, types: array<string, array{items: int, executed: int, percent: int, target: int, actual: int}>}
     */
    public static function for(int $year): array
    {
        $rows = AnnualPlanItem::query()->where('year', $year)->withOutcome()->get()->map(fn (AnnualPlanItem $item): array => [
            'department' => (string) ($item->department ?? __('registers.plan.unspecified')),
            'type' => (string) ($item->type ?? __('registers.plan.unspecified')),
            'executed' => $item->isExecuted(),
            'target' => (int) $item->target_beneficiaries,
            // المستفيدون الفعليون من البنود المنفذة وحدها
            'actual' => $item->isExecuted() ? $item->actualBeneficiaries() : 0,
        ]);

        return [
            'overall' => self::summary($rows),
            'departments' => $rows->groupBy('department')->map(fn (Collection $group): array => self::summary($group))->sortKeys()->all(),
            'types' => $rows->groupBy('type')->map(fn (Collection $group): array => self::summary($group))->sortKeys()->all(),
        ];
    }

    /**
     * @param  Collection<int, array{department: string, type: string, executed: bool, target: int, actual: int}>  $rows
     * @return array{items: int, executed: int, percent: int, target: int, actual: int}
     */
    private static function summary(Collection $rows): array
    {
        $items = $rows->count();
        $executed = $rows->where('executed', true)->count();

        return [
            'items' => $items,
            'executed' => $executed,
            'percent' => $items === 0 ? 0 : (int) round($executed * 100 / $items),
            'target' => (int) $rows->sum('target'),
            'actual' => (int) $rows->sum('actual'),
        ];
    }
}
