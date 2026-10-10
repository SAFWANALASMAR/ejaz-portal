<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع اجتماع الجمعية العمومية (requirements/04).
 */
enum MeetingType: string implements HasLabel
{
    case Ordinary = 'ordinary';
    case Extraordinary = 'extraordinary';

    public function getLabel(): string
    {
        return __('assembly.meeting_types.'.$this->value);
    }
}
