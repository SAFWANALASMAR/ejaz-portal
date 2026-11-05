<?php

namespace App\Support;

use App\Enums\LibrarySection;
use App\Enums\SubmissionStatus;
use App\Models\CompetitionSubmission;
use App\Models\DailyVisit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

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
 * - المكتبة بعدد المرات (docs/decisions/2026-11-03-portal-stats-counts.md): قراءات الكتب والأبحاث، ومشاهدات المقاطع المرئية والصوتية،
 *   من صفوف library_progress («قراءاتي») باستعلام واحد: كل قارئ لمادة مرة. ومن اختار «لا تحفظ قراءاتي» لا صف له، فلا يدخل في العدد.
 */
final class HomePortalStats
{
    public const DAYS = 30;

    public const KEY = 'portal.home-stats';

    /** ثواني التشغيل التي يُعدّ بعدها المقطع مشاهدة */
    public const MIN_VIEW_SECONDS = 10;

    /**
     * @return array{visits: int, registrants: int, book_reads: int, media_views: int, days: list<int|null>}
     */
    public static function compute(): array
    {
        $today = now(config('app.display_timezone'))->startOfDay();
        $from = $today->copy()->subDays(self::DAYS - 1);
        $first = DailyVisit::query()->min('date');
        $rows = DailyVisit::query()->where('date', '>=', $from->toDateString())->toBase()->pluck('visitors', 'date');

        $library = self::libraryCounts();
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
            'book_reads' => $library['book_reads'],
            'media_views' => $library['media_views'],
            'days' => $days,
        ];
    }

    /**
     * قراءات الكتب والأبحاث ومشاهدات المقاطع المرئية والصوتية بعدد المرات: صف «قراءاتي» لكل قارئ ومادة، فقارئ الكتاب قراءة
     * واحدة مهما قرأ من صفحاته. والصف بلا صفحة ولا ثانية (فُتحت المادة ولم يُقرأ منها شيء) لا يُعدّ، والمقطع لا يُعدّ قبل عشر ثوانٍ
     * من تشغيله، فلا تُنفخ المشاهدات بثانية لكل مقطع (مراجعة المشرف على #182، L2).
     *
     * @return array{book_reads: int, media_views: int}
     */
    private static function libraryCounts(): array
    {
        $books = [LibrarySection::Books->value, LibrarySection::Research->value];
        $media = [LibrarySection::Videos->value, LibrarySection::Audio->value];
        $in = fn (array $values): string => implode(',', array_fill(0, count($values), '?'));

        $row = DB::table('library_progress')
            ->join('library_items', 'library_items.id', '=', 'library_progress.library_item_id')
            ->selectRaw('count(case when library_items.section in ('.$in($books).') and library_progress.pages_count > 0 then 1 end) as book_reads', $books)
            ->selectRaw('count(case when library_items.section in ('.$in($media).') and library_progress.seconds_consumed >= ? then 1 end) as media_views', [...$media, self::MIN_VIEW_SECONDS])
            ->first();

        return [
            'book_reads' => (int) ($row->book_reads ?? 0),
            'media_views' => (int) ($row->media_views ?? 0),
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
     * ما يظهر في الرئيسية من المخزّن: الأرقام (والصفر لا يظهر)، والرسم.
     *
     * @param  array{visits: int, registrants: int, book_reads: int, media_views: int, days: list<int|null>}|null  $stats
     * @return array{visits: int, registrants: int, book_reads: int, media_views: int, chart: array{line: string, area: string, total: int, max: int}|null}
     */
    private static function shown(?array $stats): array
    {
        return [
            'visits' => $stats['visits'] ?? 0,
            'registrants' => $stats['registrants'] ?? 0,
            'book_reads' => $stats['book_reads'] ?? 0,
            'media_views' => $stats['media_views'] ?? 0,
            'chart' => $stats !== null ? self::chart($stats['days']) : null,
        ];
    }

    /**
     * @return array{visits: int, registrants: int, book_reads: int, media_views: int, days: list<int|null>}|null
     */
    public static function cached(): ?array
    {
        $stored = Cache::get(self::KEY);

        if (! is_array($stored) || ! is_int($stored['visits'] ?? null) || ! is_int($stored['registrants'] ?? null) || ! is_array($stored['days'] ?? null)) {
            return null;
        }

        // المخزّن قبل عدد المرات (بالصفحات والدقائق أو بلاهما) يبقى صالحاً بصفر للمكتبة حتى الحساب التالي، فلا يختفي القسم بعد النشر
        /** @var list<int|null> $days */
        $days = $stored['days'];

        return [
            'visits' => $stored['visits'],
            'registrants' => $stored['registrants'],
            'book_reads' => is_int($stored['book_reads'] ?? null) ? $stored['book_reads'] : 0,
            'media_views' => is_int($stored['media_views'] ?? null) ? $stored['media_views'] : 0,
            'days' => $days,
        ];
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
