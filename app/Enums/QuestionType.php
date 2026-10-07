<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع أسئلة المسابقة (requirements/06): نصية، واختيار من متعدد يُصحَّح آلياً، ورفع فيديو.
 */
enum QuestionType: string implements HasLabel
{
    case Text = 'text';
    case MultipleChoice = 'multiple_choice';
    case Video = 'video';

    public function getLabel(): string
    {
        return __('competitions.question_types.'.$this->value);
    }
}
