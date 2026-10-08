<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة الاجتماع (requirements/04): مجدول، ثم منعقد أو ملغى، ولا رجوع عنهما.
 */
enum MeetingStatus: string implements HasColor, HasLabel
{
    case Scheduled = 'scheduled';
    case Held = 'held';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('assembly.meeting_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::Held => 'success',
            self::Cancelled => 'gray',
        };
    }
}
