<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة المشاركة: مسودة يعدّلها المتقدم حتى الإغلاق، ثم مرسلة مقفلة برقم مرجعي.
 */
enum SubmissionStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';

    public function getLabel(): string
    {
        return __('competitions.submission_statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'success',
        };
    }
}
