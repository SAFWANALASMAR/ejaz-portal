<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * ما تجتمع تحته أسئلة في مسابقة الطلاب: نص مقروء، أو مقطع يوتيوب مضمّن (لا ملف مرفوع).
 */
enum PassageKind: string implements HasLabel
{
    case Text = 'text';
    case Video = 'video';

    public function getLabel(): string
    {
        return __('competitions.student.passage_kinds.'.$this->value);
    }
}
