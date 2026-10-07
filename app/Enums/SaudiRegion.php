<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * المناطق الإدارية في المملكة، يختار منها المتقدم منطقته (لوحة متابعة المسابقة).
 */
enum SaudiRegion: string implements HasLabel
{
    case Riyadh = 'riyadh';
    case Makkah = 'makkah';
    case Madinah = 'madinah';
    case Qassim = 'qassim';
    case Eastern = 'eastern';
    case Asir = 'asir';
    case Tabuk = 'tabuk';
    case Hail = 'hail';
    case NorthernBorders = 'northern_borders';
    case Jazan = 'jazan';
    case Najran = 'najran';
    case Bahah = 'bahah';
    case Jouf = 'jouf';

    public function getLabel(): string
    {
        return __('competitions.regions.'.$this->value);
    }
}
