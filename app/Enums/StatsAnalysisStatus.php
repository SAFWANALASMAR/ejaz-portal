<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة تقرير تحليل الإحصائيات: في الطابور، أو جاهز، أو تعذّر (المزود، أو السقف، أو أُطفئ التحليل قبل دوره).
 */
enum StatsAnalysisStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Done = 'done';
    case Failed = 'failed';

    public function getLabel(): string
    {
        return __('analysis.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Queued => 'warning',
            self::Done => 'success',
            self::Failed => 'danger',
        };
    }
}
