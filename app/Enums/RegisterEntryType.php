<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نوع المستند في السجلات ذات الصلاحية (requirements/13 §1).
 */
enum RegisterEntryType: string implements HasLabel
{
    case OfficialDocument = 'official_document';
    case Contract = 'contract';
    case License = 'license';
    case Partnership = 'partnership';
    case Property = 'property';
    case Investment = 'investment';
    case ActivityApproval = 'activity_approval';
    case Permit = 'permit';

    public function getLabel(): string
    {
        return __('registers.types.'.$this->value);
    }
}
