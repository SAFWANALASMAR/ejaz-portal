<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة محاولة الطالب في مرحلة: جارية حتى التسليم أو انتهاء الوقت، ثم مسلَّمة مقفلة.
 */
enum AttemptStatus: string implements HasColor, HasLabel
{
    case InProgress = 'in_progress';
    case Submitted = 'submitted';

    public function getLabel(): string
    {
        return __('competitions.student.attempt_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::InProgress => 'warning',
            self::Submitted => 'success',
        };
    }
}
