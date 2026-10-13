<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع حقول نموذج الفرصة التي يحددها المسؤول (Opening::$form_fields).
 */
enum FormFieldType: string implements HasLabel
{
    case Text = 'text';
    case Textarea = 'textarea';
    case Email = 'email';
    case Number = 'number';
    case Date = 'date';
    case Select = 'select';
    case Pdf = 'pdf';

    public function getLabel(): string
    {
        return __('services.field_types.'.$this->value);
    }
}
