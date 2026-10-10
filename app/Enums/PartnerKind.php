<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع الشريك في مقطع «الشركاء والرعاة» (ملاحظة المدير التنفيذي — requirements/12، القسم 3).
 */
enum PartnerKind: string implements HasLabel
{
    case Partner = 'partner';
    case Sponsor = 'sponsor';

    public function getLabel(): string
    {
        return __('partners.kinds.'.$this->value);
    }
}
