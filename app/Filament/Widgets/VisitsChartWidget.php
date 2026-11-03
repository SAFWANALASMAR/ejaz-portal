<?php

namespace App\Filament\Widgets;

use App\Models\DailyVisit;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

use function Filament\Support\generate_icon_html;

/**
 * رسم الزيارات في آخر 30 يوماً بتوقيت الرياض (docs/plans/admin-stats.md): المشاهدات والزوار الفريدون لكل يوم من
 * daily_visits التي ينقلها ejaz:refresh-stats كل عشر دقائق. بـ Chart.js الذي مع Filament، بلا مكتبة ولا استطلاع.
 * واليوم قبل أول يوم عُدّ فيه بلا رقم لا صفر، فلا يُنسب إلى العدّاد ما قبل وجوده.
 */
class VisitsChartWidget extends ChartWidget
{
    private const DAYS = 30;

    protected static ?int $sort = 5;

    // ثلاثون صفاً صغيراً بفهرس التاريخ، فيُعرض مع الصفحة لا بطلب مؤجل
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '18rem';

    public static function canView(): bool
    {
        return auth()->user()?->can('stats.view') ?? false;
    }

    /**
     * العنوان بمربع أيقونة بدل الشريط كبقية عناصر الرئيسية (البند N2 من تدقيق القبول). قالب الرسم في Filament لا يمرر أيقونة
     * لقسمه، فالمربع داخل العنوان نفسه (ej-heading-tile في سمة اللوحة)، والنص مهرَّب.
     */
    public function getHeading(): Htmlable
    {
        $icon = generate_icon_html(Heroicon::OutlinedChartBar, size: IconSize::Large)?->toHtml() ?? '';

        return new HtmlString('<span class="ej-heading-tile" aria-hidden="true">'.$icon.'</span>'
            .'<span>'.e(__('surveys.stats.visits_chart.heading', ['days' => self::DAYS])).'</span>');
    }

    public function getDescription(): string
    {
        return __('surveys.stats.visits_chart.description');
    }

    public function getEmptyStateHeading(): string
    {
        return __('surveys.stats.visits_chart.empty');
    }

    /**
     * ما في الرسم نصاً لقارئ الشاشة: مجموع المشاهدات، وأكثر الأيام مشاهدة.
     */
    public function getChartAssistiveContent(): string
    {
        /** @var list<int|null> $views */
        $views = $this->getCachedData()['datasets'][0]['data'] ?? [];
        /** @var list<string> $labels */
        $labels = $this->getCachedData()['labels'] ?? [];
        $max = max([0, ...array_filter($views, is_int(...))]);

        if ($max === 0) {
            return __('surveys.stats.visits_chart.summary_none', ['days' => self::DAYS]);
        }

        return __('surveys.stats.visits_chart.summary', [
            'days' => self::DAYS,
            'views' => number_format((int) array_sum($views)),
            'day' => $labels[(int) array_search($max, $views, true)] ?? '',
            'max' => number_format($max),
        ]);
    }

    protected function getType(): string
    {
        return 'line';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $first = DailyVisit::query()->min('date');

        if ($first === null) {
            return [];
        }

        $today = now(config('app.display_timezone'))->startOfDay();
        $from = $today->copy()->subDays(self::DAYS - 1);
        $rows = DailyVisit::query()->where('date', '>=', $from->toDateString())->get()->keyBy(fn (DailyVisit $visit): string => $visit->date->toDateString());

        $labels = $views = $visitors = [];

        for ($day = $from; $day->lte($today); $day = $day->copy()->addDay()) {
            $row = $rows->get($day->toDateString());
            $counted = $day->toDateString() >= (string) $first;

            $labels[] = $day->day.' '.__('dates.months.'.$day->month);
            $views[] = $row->views ?? ($counted ? 0 : null);
            $visitors[] = $row->visitors ?? ($counted ? 0 : null);
        }

        return [
            'datasets' => [
                // درجتان من الأزرق وحده (البند G1)، وكلتاهما فوق 3:1 على الخلفية الفاتحة
                ['label' => __('surveys.stats.visits_chart.views'), 'data' => $views, 'borderColor' => '#3E8ACA'],
                ['label' => __('surveys.stats.visits_chart.visitors'), 'data' => $visitors, 'borderColor' => '#1F4565'],
            ],
            'labels' => $labels,
        ];
    }

    /**
     * من اليمين: الأيام من اليمين إلى اليسار بتسميات أفقية يُتخطى بعضها إن ضاق العرض، والأرقام على اليمين
     * وبالأرقام نفسها في اللوحة. والنسبة أقل من الافتراضي فلا يصغر الرسم على الجوال، والحد الأعلى للارتفاع
     * ($maxHeight) يمنع طوله على الحاسب.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'locale' => 'ar-SA-u-nu-latn',
            'aspectRatio' => 1.25,
            'scales' => [
                'x' => ['reverse' => true, 'offset' => true, 'ticks' => ['maxRotation' => 0, 'autoSkipPadding' => 12]],
                'y' => ['position' => 'right', 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => [
                'legend' => ['rtl' => true],
                'tooltip' => ['rtl' => true],
            ],
        ];
    }
}
