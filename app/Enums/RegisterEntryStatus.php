<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة المستند: تُحسب من تاريخ انتهائه ومدة تنبيهه ولا تُخزَّن (RegisterEntry::status()).
 */
enum RegisterEntryStatus: string implements HasColor, HasLabel
{
    case Valid = 'valid';
    case Expiring = 'expiring';
    case Expired = 'expired';
    case NoExpiry = 'no_expiry';

    public function getLabel(): string
    {
        return __('registers.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Valid => 'success',
            self::Expiring => 'warning',
            self::Expired => 'danger',
            self::NoExpiry => 'gray',
        };
    }
}
