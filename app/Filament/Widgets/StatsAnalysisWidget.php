<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\StatsAnalysisPage;
use Filament\Widgets\Widget;

/**
 * زر «تحليل» في لوحة الإحصائيات (رئيسية اللوحة) إلى صفحة التحليل، متى شُغّل التحليل ولمن له stats.view.
 */
class StatsAnalysisWidget extends Widget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.stats-analysis';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return StatsAnalysisPage::canAccess();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return ['url' => StatsAnalysisPage::getUrl()];
    }
}
