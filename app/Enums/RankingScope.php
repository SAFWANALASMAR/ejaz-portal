<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * نطاق الترتيب التراكمي والجوائز في مسابقة الطلاب: لكل جنس من كل الفئات معاً (المطوية، والافتراضي)، أو لكل جنس في
 * كل فئة على حدة. معلّق بسؤال المدير التنفيذي (open-questions.md)، فيُختار لكل مسابقة.
 */
enum RankingScope: string implements HasLabel
{
    case All = 'all';
    case Category = 'category';

    public function getLabel(): string
    {
        return __('competitions.student.ranking_scopes.'.$this->value);
    }
}
