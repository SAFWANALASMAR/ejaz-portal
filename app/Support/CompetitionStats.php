<?php

namespace App\Support;

use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use Illuminate\Support\Facades\Cache;

/**
 * أرقام لوحة متابعة المسابقة: المسجلون والمسودات والمكتملون، وتوزيع المكتملين حسب الفئة والمنطقة.
 * تُحسب بالتجميع في قاعدة البيانات، وتُخزَّن دقيقة ثم تُحدَّث في الخلفية بعد الرد (Cache::flexible)،
 * فلا تُحسب عند كل زيارة ولا ينتظرها أحد وقت الذروة (performance.md).
 */
final class CompetitionStats
{
    /**
     * @return array{registered: int, drafts: int, submitted: int, categories: array<string, int>, regions: array<string, int>}
     */
    public static function for(Competition $competition): array
    {
        return Cache::flexible('competitions.stats.'.$competition->id, [60, 600], fn (): array => self::compute($competition));
    }

    /**
     * @return array{registered: int, drafts: int, submitted: int, categories: array<string, int>, regions: array<string, int>}
     */
    public static function compute(Competition $competition): array
    {
        $statuses = $competition->submissions()->toBase()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $submitted = fn () => $competition->submissions()->toBase()->where('status', SubmissionStatus::Submitted->value);

        $categories = $submitted()->selectRaw('category, count(*) as total')->groupBy('category')->orderByDesc('total')->pluck('total', 'category');
        $regions = $submitted()->selectRaw('region, count(*) as total')->groupBy('region')->orderByDesc('total')->pluck('total', 'region');

        return [
            'registered' => (int) $statuses->sum(),
            'drafts' => (int) ($statuses[SubmissionStatus::Draft->value] ?? 0),
            'submitted' => (int) ($statuses[SubmissionStatus::Submitted->value] ?? 0),
            'categories' => $categories->mapWithKeys(fn ($total, $category): array => [(string) ($category ?: __('competitions.stats.none')) => (int) $total])->all(),
            'regions' => $regions->mapWithKeys(fn ($total, $region): array => [(SaudiRegion::tryFrom((string) $region)?->getLabel() ?? __('competitions.stats.none')) => (int) $total])->all(),
        ];
    }
}
