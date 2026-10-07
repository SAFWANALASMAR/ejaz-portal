<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع أسئلة الاستبيان: مقياس من 1 إلى 5، واختيار، ونص.
 */
enum SurveyQuestionType: string implements HasLabel
{
    case Scale = 'scale';
    case Choice = 'choice';
    case Text = 'text';

    public function getLabel(): string
    {
        return __('surveys.question_types.'.$this->value);
    }
}
