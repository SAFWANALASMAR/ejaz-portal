<?php

namespace App\Support;

use App\Enums\SubmissionStatus;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

/**
 * أرقام «البوابة بالأرقام» العامة في الرئيسية (البند H9 — docs/decisions/2026-11-01-site-stats-v2.md): مجاميع وحدها من
 * الجداول القائمة، بلا بيانات شخصية. تُحسب في الخلفية مع إحصائيات البوابة (ejaz:refresh-stats كل عشر دقائق) وتُخزَّن،
 * والرئيسية تقرأ المخزّن وحده، فلا استعلام عند الطلب.
 *
 * - الزيارات: مجموع الزوار الفريدين لكل يوم في daily_visits (عدّاد Visits)، فمن زار في يومين يُعدّ زيارتين.
 * - المسجلون في المسابقات: أصحاب مشاركات قائمة (غير ملغاة) في مسابقات منشورة، المسجَّلة في مسابقة الطلاب أو المرسلة في غيرها،
 *   والمسجل في مسابقتين يُعدّ مرة.
 * - الرسم: الزوار الفريدون لكل يوم من آخر 30 يوماً بتوقيت الرياض، واليوم قبل أول يوم عُدّ فيه بلا رقم لا صفر.
 *
 * وقراءات الكتب ومشاهدات المقاطع لا تُسجَّل في أي جدول، فلا تظهر هنا حتى يُبنى عدّادها في فرعه.
 */
final class HomePortalStats
{
    public const DAYS = 30;

    private const KEY = 'portal.home-stats';

    /**
     * @return array{visits: int, registrants: int, days: list<int|null>}
     */
    public static function compute(): array
    {
        $today = now(config('app.display_timezone'))->startOfDay();
        $from = $today->copy()->subDays(self::DAYS - 1);
        $first = DailyVisit::query()->min('date');
        $rows = DailyVisit::query()->where('date', '>=', $from->toDateString())->toBase()->pluck('visitors', 'date');

        $days = [];

        for ($day = $from; $day->lte($today); $day = $day->copy()->addDay()) {
            $date = $day->toDateString();
            $days[] = isset($rows[$date]) ? (int) $rows[$date] : ($first !== null && $date >= (string) $first ? 0 : null);
        }

        return [
            'visits' => (int) DailyVisit::query()->sum('visitors'),
            'registrants' => CompetitionSubmission::query()
                ->whereHas('competition', fn (Builder $competition) => $competition->published())
                ->where(fn (Builder $submission) => $submission->whereNotNull('registered_at')->orWhere('status', SubmissionStatus::Submitted->value))
                ->distinct()->count('user_id'),
            'days' => $days,
        ];
    }

    /**
     * يحسب ويخزّن، ويعيد هل تغيّر ما تعرضه الرئيسية منه (فتُمسح ذاكرتها — RefreshPortalStats). فالأصفار قبل أول زيارة وأول
     * حساب بعد النشر بلا بيانات لا تمسح شيئاً.
     */
    public static function refresh(): bool
    {
        $previous = self::cached();
        $current = self::compute();

        // بلا مدة كإحصائيات البوابة: يبقى آخر حساب حتى يحل محله التالي
        Cache::forever(self::KEY, $current);

        return self::shown($previous) !== self::shown($current);
    }

    /**
     * ما يظهر في الرئيسية من المخزّن: الرقمان (والصفر لا يظهر)، والرسم.
     *
     * @param  array{visits: int, registrants: int, days: list<int|null>}|null  $stats
     * @return array{visits: int, registrants: int, chart: array{line: string, area: string, total: int, max: int}|null}
     */
    private static function shown(?array $stats): array
    {
        return [
            'visits' => $stats['visits'] ?? 0,
            'registrants' => $stats['registrants'] ?? 0,
            'chart' => $stats !== null ? self::chart($stats['days']) : null,
        ];
    }

    /**
     * @return array{visits: int, registrants: int, days: list<int|null>}|null
     */
    public static function cached(): ?array
    {
        $stored = Cache::get(self::KEY);

        if (! is_array($stored) || ! is_int($stored['visits'] ?? null) || ! is_int($stored['registrants'] ?? null) || ! is_array($stored['days'] ?? null)) {
            return null;
        }

        /** @var array{visits: int, registrants: int, days: list<int|null>} $stored */
        return $stored;
    }

    /**
     * نقاط الرسم في مربع 1000×220 (viewBox)، من اليمين إلى اليسار: اليوم الأقدم يميناً واليوم يساراً كاتجاه القراءة. والأيام
     * قبل أول يوم عُدّ فيه لا تُرسم. وبلا زيارة في الأيام المعدودة كلها، أو بأقل من يومين معدودين (أول يوم بعد النشر)، لا رسم.
     *
     * @param  list<int|null>  $days
     * @return array{line: string, area: string, total: int, max: int}|null
     */
    public static function chart(array $days): ?array
    {
        $counted = array_filter($days, is_int(...));
        $max = $counted === [] ? 0 : max($counted);

        if ($max === 0 || count($counted) < 2) {
            return null;
        }

        $last = count($days) - 1;
        $points = [];

        foreach ($days as $index => $value) {
            if ($value === null) {
                continue;
            }

            $x = $last === 0 ? 0 : round(1000 - ($index / $last) * 1000, 1);
            // هامش علوي 14 فلا يُقص الخط عند القمة
            $y = round(220 - ($value / $max) * 206, 1);
            $points[] = $x.','.$y;
        }

        $line = implode(' ', $points);
        $start = explode(',', $points[0])[0];
        $end = explode(',', $points[array_key_last($points)])[0];

        return [
            'line' => $line,
            'area' => $start.',220 '.$line.' '.$end.',220',
            'total' => array_sum($counted),
            'max' => $max,
        ];
    }
}
