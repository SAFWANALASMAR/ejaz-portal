<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع حقول النماذج الإلكترونية (requirements/05): نص، ورقم، وتاريخ، واختيار، ومرفق، وتوقيع.
 * منفصلة عن حقول الفرص والبرامج (FormFieldType)، فلا يمسها التوقيع.
 */
enum DocumentFieldType: string implements HasLabel
{
    case Text = 'text';
    case LongText = 'long_text';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case File = 'file';
    case Signature = 'signature';

    public function getLabel(): string
    {
        return __('documents.field_types.'.$this->value);
    }
}
