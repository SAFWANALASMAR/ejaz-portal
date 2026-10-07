<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة البرنامج المعروضة (requirements/03 والصورة 06): تُحسب من مواعيده ومقاعده ولا تُخزَّن
 * (Program::status()).
 */
enum ProgramStatus: string implements HasColor, HasLabel
{
    case Soon = 'soon';
    case Open = 'open';
    case Full = 'full';
    case Ended = 'ended';

    public function getLabel(): string
    {
        return __('programs.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open => 'success',
            self::Soon => 'warning',
            self::Full, self::Ended => 'gray',
        };
    }
}
