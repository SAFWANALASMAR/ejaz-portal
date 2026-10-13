<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة التسجيل في برنامج: مؤكد يشغل مقعداً، أو في قائمة الانتظار، أو ملغى.
 */
enum RegistrationStatus: string implements HasColor, HasLabel
{
    case Confirmed = 'confirmed';
    case Waitlisted = 'waitlisted';
    case Cancelled = 'cancelled';

    public function getLabel(): string
    {
        return __('programs.registration_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Confirmed => 'success',
            self::Waitlisted => 'warning',
            self::Cancelled => 'gray',
        };
    }
}
