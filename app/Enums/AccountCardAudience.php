<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * لمن تظهر بطاقة لوحة المستفيد (AccountCard): الجميع، أو من يدخل لوحة الإدارة. وبطاقة القسم المبني (route) تتبع
 * صلاحية قسمها أيضاً (AccountNavigation).
 */
enum AccountCardAudience: string implements HasLabel
{
    case Everyone = 'everyone';
    case Panel = 'panel';

    public function getLabel(): string
    {
        return __('account_cards.audiences.'.$this->value);
    }
}
