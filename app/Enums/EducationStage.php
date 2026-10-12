<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * المرحلة التعليمية في تسجيل الاختبار الإلكتروني، بالقائمة الواردة في متطلبات المدير التنفيذي حرفياً.
 */
enum EducationStage: string implements HasLabel
{
    case Intermediate = 'intermediate';
    case Secondary = 'secondary';
    case University = 'university';
    case ScienceEnthusiasts = 'science_enthusiasts';

    public function getLabel(): string
    {
        return __('competitions.education_stages.'.$this->value);
    }
}
