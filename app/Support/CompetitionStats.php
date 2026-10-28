<?php

namespace App\Support;

use App\Enums\AttemptStatus;
use App\Enums\SaudiRegion;
use App\Enums\SubmissionStatus;
use App\Models\Competition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
        if ($competition->student_mode) {
            return self::student($competition);
        }

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

    /**
     * مسابقة الطلاب (الملاحظة 9 من الاختبار الشامل 0.14): لا مسودات فيها ولا إرسال، بل تسجيل ثم محاولة لكل فرع. فالمسجلون
     * من سجّلوا، و«الجارية» المحاولات التي لم تُسلَّم، و«المكتملة» المحاولات المسلَّمة، وتوزيعها بفئة الطالب ومنطقته من
     * قائمتي المسابقة. والتسجيل الملغى (المحذوف) خارج الأرقام.
     *
     * @return array{registered: int, drafts: int, submitted: int, categories: array<string, int>, regions: array<string, int>}
     */
    private static function student(Competition $competition): array
    {
        $attempts = fn () => DB::table('competition_attempts as a')
            ->join('competition_rounds as r', 'r.id', '=', 'a.round_id')
            ->join('competition_submissions as s', 's.id', '=', 'a.submission_id')
            ->where('r.competition_id', $competition->id)
            ->whereNull('s.deleted_at');

        $statuses = $attempts()->selectRaw('a.status, count(*) as total')->groupBy('a.status')->pluck('total', 'status');
        $submitted = fn () => $attempts()->where('a.status', AttemptStatus::Submitted->value);

        $categories = $submitted()->leftJoin('competition_categories as c', 'c.id', '=', 's.category_id')
            ->selectRaw('c.name, count(*) as total')->groupBy('c.name')->orderByDesc('total')->pluck('total', 'name');
        $regions = $submitted()->leftJoin('competition_regions as g', 'g.id', '=', 's.region_id')
            ->selectRaw('g.name, count(*) as total')->groupBy('g.name')->orderByDesc('total')->pluck('total', 'name');
        $label = fn ($total, $name): array => [(string) ($name ?: __('competitions.stats.none')) => (int) $total];

        return [
            'registered' => $competition->submissions()->whereNotNull('registered_at')->count(),
            'drafts' => (int) ($statuses[AttemptStatus::InProgress->value] ?? 0),
            'submitted' => (int) ($statuses[AttemptStatus::Submitted->value] ?? 0),
            'categories' => $categories->mapWithKeys($label)->all(),
            'regions' => $regions->mapWithKeys($label)->all(),
        ];
    }
}
