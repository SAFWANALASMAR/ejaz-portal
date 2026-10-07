<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * أيقونات خانات الإحصائيات في الرئيسية. القيمة اسم أيقونة Heroicons الخطية
 * (heroicon-o-<القيمة>)، فلا يُبنى اسم ملف من مدخل حر.
 */
enum StatIcon: string implements HasLabel
{
    case Users = 'users';
    case AcademicCap = 'academic-cap';
    case BookOpen = 'book-open';
    case Calendar = 'calendar';
    case Trophy = 'trophy';
    case HandRaised = 'hand-raised';
    case BuildingLibrary = 'building-library';
    case LightBulb = 'light-bulb';

    public function getLabel(): string
    {
        return __('settings.icons.'.$this->value);
    }
}
