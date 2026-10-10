<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * صفة العضو في مجلس الإدارة أو اللجنة.
 */
enum BodyRole: string implements HasLabel
{
    case Chair = 'chair';
    case ViceChair = 'vice_chair';
    case Secretary = 'secretary';
    case Member = 'member';

    public function getLabel(): string
    {
        return __('registers.body_roles.'.$this->value);
    }
}
