<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع حقول النماذج الإلكترونية (requirements/05): نص، ورقم، وتاريخ، واختيار، ومرفق، وتوقيع. ولنسخ
 * النموذج الورقي (requirements/12 البند 10): جدول بصفوف يضيفها المعبّئ، وعنوان قسم ونص ثابت بلا إجابة.
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
    case Table = 'table';
    case Heading = 'heading';
    case Paragraph = 'paragraph';

    public function getLabel(): string
    {
        return __('documents.field_types.'.$this->value);
    }

    /**
     * عنصر تخطيط يُعرض ولا يأخذ إجابة.
     */
    public function isLayout(): bool
    {
        return $this === self::Heading || $this === self::Paragraph;
    }
}
