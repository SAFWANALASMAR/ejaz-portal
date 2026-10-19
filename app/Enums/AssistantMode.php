<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * كيف أُجيب السؤال: بالمزود من المقاطع، أو بالدليل وحده (بلا مزود أو بعد السقف الشهري)، أو لم يُوجد محتوى
 * مطابق فلم يُرسل شيء، أو تعذّر المزود (مشغول، أو مهلة، أو خطأ).
 */
enum AssistantMode: string implements HasLabel
{
    case Generated = 'generated';
    case Guide = 'guide';
    case NoMatch = 'no_match';
    case Busy = 'busy';

    public function getLabel(): string
    {
        return __('assistant.modes.'.$this->value);
    }
}
