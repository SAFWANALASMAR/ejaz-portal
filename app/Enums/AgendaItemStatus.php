<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة تنفيذ قرار بند في جدول أعمال اجتماع.
 */
enum AgendaItemStatus: string implements HasColor, HasLabel
{
    case NotStarted = 'not_started';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function getLabel(): string
    {
        return __('registers.agenda_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NotStarted => 'gray',
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }
}
