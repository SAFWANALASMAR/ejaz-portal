<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع حقول التسجيل المخصصة في مسابقة الطلاب: تُضاف من اللوحة بلا برمجة.
 */
enum CompetitionFieldType: string implements HasLabel
{
    case Text = 'text';
    case Number = 'number';
    case Select = 'select';

    public function getLabel(): string
    {
        return __('competitions.student.field_types.'.$this->value);
    }
}
