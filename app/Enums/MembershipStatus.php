<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة العضوية في الجمعية العمومية. الفعّالة وحدها تمنح دور عضو الجمعية العمومية
 * (SaveAssemblyMembership)، فالموقوفة والمنتهية بلا وصول إلى منطقة الأعضاء.
 */
enum MembershipStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    public function getLabel(): string
    {
        return __('assembly.membership_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Suspended => 'warning',
            self::Ended => 'gray',
        };
    }
}
