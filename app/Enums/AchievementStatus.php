<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * حالة نشر الإنجاز: المسودة في اللوحة وحدها، والمنشور في الصفحة العامة وبوابة المجلس والجمعية العمومية.
 */
enum AchievementStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Published = 'published';

    public function getLabel(): string
    {
        return __('achievements.statuses.'.$this->value);
    }

    public function getColor(): string
    {
        return $this === self::Published ? 'success' : 'gray';
    }
}
