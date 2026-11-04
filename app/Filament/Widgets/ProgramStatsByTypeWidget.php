<?php

namespace App\Filament\Widgets;

use App\Support\ProgramStatsByType;
use Filament\Support\Enums\IconSize;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;

use function Filament\Support\generate_icon_html;

/**
 * رسم مقارنة بين أنواع البرامج (البند R23 — docs/decisions/2026-11-02-program-stats-by-type.md): المسجلون والمكملون لكل نوع،
 * ونسبة الإكمال والصفحات والدقائق في الوصف تحته. من المخزّن الذي يحسبه ejaz:refresh-stats، كرسم الزيارات بـ Chart.js الذي مع
 * Filament، بلا استعلام عند الطلب ولا استطلاع. لمن له stats.view.
 */
class ProgramStatsByTypeWidget extends ChartWidget
{
    protected static ?int $sort = 7;

    // قراءة واحدة من الذاكرة المؤقتة، فتُعرض مع الصفحة لا بطلب مؤجل
    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $maxHeight = '18rem';

    public static function canView(): bool
    {
        return auth()->user()?->can('stats.view') ?? false;
    }

    /**
     * المخزّن مقروءاً مرة في الطلب للوصف والرسم ونصه (خاصية غير عامة، فلا تُرسل مع حالة Livewire).
     *
     * @var list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>|false|null
     */
    private array|false|null $rows = false;

    /**
     * @return list<array{key: string, label: string, registered: int, completed: int, rate: int, pages: int|null, minutes: int|null}>|null
     */
    private function rows(): ?array
    {
        if ($this->rows === false) {
            $this->rows = ProgramStatsByType::cached();
        }

        return $this->rows;
    }

    /**
     * العنوان بمربع أيقونة كرسم الزيارات وبقية عناصر الرئيسية (طلب لوحة الإدارة، 4 نوفمبر 2026 — الدفعة ٢)، كان بالشريط وحده.
     */
    public function getHeading(): Htmlable
    {
        $icon = generate_icon_html(Heroicon::OutlinedAcademicCap, size: IconSize::Large)?->toHtml() ?? '';

        return new HtmlString('<span class="ej-heading-tile" aria-hidden="true">'.$icon.'</span>'
            .'<span>'.e(__('program_stats.heading')).'</span>');
    }

    public function getDescription(): string|Htmlable|null
    {
        $rows = $this->rows();

        return $rows === null || $rows === [] || $this->allZero()
            ? __('program_stats.description')
            // HtmlString لا View: الوصف يُطبع بـ {{ }} في القسم فيُهرَّب ما ليس Htmlable صراحةً (اختبار المتصفح)
            : new HtmlString(view('filament.widgets.program-stats-by-type-summary', ['rows' => $rows])->render());
    }

    public function getEmptyStateHeading(): string
    {
        return __('program_stats.empty');
    }

    /**
     * فارغ قبل أول حساب، وكذلك إن كانت الأنواع كلها أصفاراً: لا محوران فارغان ولا فقرة أصفار (ملاحظة المقيّم على #168).
     */
    public function isEmpty(): bool
    {
        return parent::isEmpty() || $this->allZero();
    }

    public function getEmptyState(): View
    {
        $computed = $this->rows() !== null && $this->rows() !== [];

        return view('filament.admin.chart-empty', [
            'heading' => $computed ? __('program_stats.summary_none') : __('program_stats.empty'),
            'body' => $computed ? __('admin.dashboard.program_stats_empty_body') : null,
        ]);
    }

    private function allZero(): bool
    {
        $rows = $this->rows() ?? [];

        return $rows !== [] && collect($rows)->every(fn (array $row): bool => $row['registered'] === 0 && $row['completed'] === 0);
    }

    /**
     * ما في الرسم نصاً لقارئ الشاشة: لكل نوع مسجلوه ومكملوه ونسبته.
     */
    public function getChartAssistiveContent(): string
    {
        $rows = $this->rows() ?? [];

        if (collect($rows)->sum('registered') === 0) {
            return __('program_stats.summary_none');
        }

        return collect($rows)->map(fn (array $row): string => __('program_stats.summary', [
            'type' => $row['label'],
            'registered' => number_format($row['registered']),
            'completed' => number_format($row['completed']),
            'rate' => $row['rate'],
        ]))->implode(' ');
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $rows = $this->rows();

        if ($rows === null || $rows === []) {
            return [];
        }

        return [
            'datasets' => [
                // درجتان من الأزرق كرسم الزيارات (البند G1)
                ['label' => __('program_stats.registered'), 'data' => array_column($rows, 'registered'), 'backgroundColor' => '#1F4565'],
                ['label' => __('program_stats.completed'), 'data' => array_column($rows, 'completed'), 'backgroundColor' => '#3E8ACA'],
            ],
            'labels' => array_column($rows, 'label'),
        ];
    }

    /**
     * من اليمين كرسم الزيارات: الأنواع من اليمين، والأرقام على اليمين.
     *
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return [
            'locale' => 'ar-SA-u-nu-latn',
            'aspectRatio' => 1.25,
            'scales' => [
                'x' => ['reverse' => true, 'ticks' => ['maxRotation' => 0]],
                'y' => ['position' => 'right', 'beginAtZero' => true, 'ticks' => ['precision' => 0]],
            ],
            'plugins' => [
                'legend' => ['rtl' => true],
                'tooltip' => ['rtl' => true],
            ],
        ];
    }
}
