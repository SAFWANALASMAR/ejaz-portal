<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أنواع الأعمال المرفوعة في المسابقة: «رفع الأعمال (صور – PDF – روابط)». ويختار المسؤول المسموح منها لكل مسابقة.
 */
enum WorkType: string implements HasLabel
{
    case Image = 'image';
    case Pdf = 'pdf';
    case Link = 'link';

    public function getLabel(): string
    {
        return __('competitions.work_types.'.$this->value);
    }
}
