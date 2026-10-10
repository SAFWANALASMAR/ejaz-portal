<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * الأيقونات التي يختارها المحرر للموقع العام: خانات الإحصائيات، وبطاقات المقاطع (القيم
 * والأهداف والرسالة والرؤية). القيمة اسم أيقونة Heroicons الخطية (heroicon-o-<القيمة>)،
 * فلا يُبنى اسم ملف من مدخل حر.
 */
enum SiteIcon: string implements HasLabel
{
    case Users = 'users';
    case AcademicCap = 'academic-cap';
    case BookOpen = 'book-open';
    case Calendar = 'calendar';
    case Trophy = 'trophy';
    case HandRaised = 'hand-raised';
    case BuildingLibrary = 'building-library';
    case LightBulb = 'light-bulb';
    case Eye = 'eye';
    case Flag = 'flag';
    case Star = 'star';
    case Heart = 'heart';
    case ShieldCheck = 'shield-check';
    case Sparkles = 'sparkles';
    case PuzzlePiece = 'puzzle-piece';
    case Beaker = 'beaker';
    case GlobeAlt = 'globe-alt';
    case CheckBadge = 'check-badge';
    case Scale = 'scale';

    public function getLabel(): string
    {
        return __('icons.'.$this->value);
    }
}
