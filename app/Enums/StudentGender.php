<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * جنس الطالب في مسابقة الطلاب (مطوية «البراهين العلمية»): حقل إلزامي، والجوائز والترتيب التراكمي لكل جنس على حدة.
 */
enum StudentGender: string implements HasLabel
{
    case Male = 'male';
    case Female = 'female';

    public function getLabel(): string
    {
        return __('competitions.student.genders.'.$this->value);
    }

    /**
     * اسم الفئة في الجوائز والترتيب: بنين أو بنات.
     */
    public function groupLabel(): string
    {
        return __('competitions.student.gender_groups.'.$this->value);
    }
}
