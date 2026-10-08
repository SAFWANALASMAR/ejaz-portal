<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة البث تُحسب من النشر وموعديه (LiveStream::status)، فتظهر صفحته وتختفي آلياً بلا مهمة مجدولة.
 * و«منقول» بعد نقل تسجيله إلى المكتبة المرئية.
 */
enum LiveStreamStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Upcoming = 'upcoming';
    case Live = 'live';
    case Ended = 'ended';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('livestream.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft, self::Ended => 'gray',
            self::Upcoming => 'warning',
            self::Live => 'danger',
            self::Archived => 'success',
        };
    }
}
